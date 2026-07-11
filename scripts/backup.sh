#!/usr/bin/env bash
#
# Tägliches Backup des VDSt-Kassensystems (Docker-Setup):
#   1. MySQL-Dump aus dem DB-Container (mysqldump läuft dort, nicht im Web-Container)
#   2. Archiv der Beleg-Dateien (public/uploads/, Bind-Mount → direkt vom Host)
#   3. Monats-Promotion: erste Sicherung des Monats wird nach monthly/ kopiert
#   4. Retention: daily 30 Tage, monthly ~12 Monate
#
# Läuft auf dem Host (kein Host-PHP nötig). Konfiguration per Umgebungsvariablen,
# Defaults passen zum lokalen docker-compose-Setup. Doku: docs/BACKUP.md
#
# Cron-Beispiel (täglich 02:00):
#   0 2 * * * DB_PASS=… /pfad/zu/kassensystem-vdst/scripts/backup.sh >> /var/log/kassensystem-backup.log 2>&1

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

BACKUP_DIR="${BACKUP_DIR:-$REPO_DIR/backups}"
DB_CONTAINER="${DB_CONTAINER:-kassensystem-vdst-db}"
DB_NAME="${DB_NAME:-vdst_kassensystem_small}"
DB_USER="${DB_USER:-kassenuser}"
DB_PASS="${DB_PASS:-kassenpass123}"
RETENTION_DAILY="${RETENTION_DAILY:-30}"
RETENTION_MONTHLY_TAGE="${RETENTION_MONTHLY_TAGE:-366}"

HEUTE="$(date +%Y-%m-%d)"
MONAT="$(date +%Y-%m)"

fehler() {
    echo "FEHLER: $*" >&2
    exit 1
}

command -v docker >/dev/null || fehler "docker nicht gefunden"
docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER" \
    || fehler "DB-Container '$DB_CONTAINER' läuft nicht"
[ -d "$REPO_DIR/public/uploads" ] || fehler "Upload-Verzeichnis $REPO_DIR/public/uploads fehlt"

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/monthly"

# ---- 1. MySQL-Dump ----------------------------------------------------------
# --no-tablespaces ist Absicht: ohne das bräuchte kassenuser auf MySQL 8 das
# PROCESS-Privileg. MYSQL_PWD vermeidet das Passwort in der Prozessliste.
DB_DUMP="$BACKUP_DIR/daily/db_$HEUTE.sql.gz"

docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" \
    mysqldump --single-transaction --no-tablespaces --routines --triggers \
    -u "$DB_USER" "$DB_NAME" | gzip > "$DB_DUMP"

[ -s "$DB_DUMP" ] || fehler "DB-Dump $DB_DUMP ist leer"
gzip -t "$DB_DUMP" || fehler "DB-Dump $DB_DUMP ist kein gültiges gzip"
zcat < "$DB_DUMP" | tail -1 | grep -q 'Dump completed' \
    || fehler "DB-Dump $DB_DUMP ist unvollständig (kein 'Dump completed')"

# ---- 2. Beleg-Dateien -------------------------------------------------------
UPLOADS_ARCHIV="$BACKUP_DIR/daily/uploads_$HEUTE.tar.gz"

tar -czf "$UPLOADS_ARCHIV" -C "$REPO_DIR/public" uploads
gzip -t "$UPLOADS_ARCHIV" || fehler "Upload-Archiv $UPLOADS_ARCHIV ist kein gültiges gzip"

# ---- 3. Monats-Promotion (selbstheilend: greift beim ersten Lauf im Monat) --
if [ ! -f "$BACKUP_DIR/monthly/db_$MONAT.sql.gz" ]; then
    cp "$DB_DUMP" "$BACKUP_DIR/monthly/db_$MONAT.sql.gz"
    cp "$UPLOADS_ARCHIV" "$BACKUP_DIR/monthly/uploads_$MONAT.tar.gz"
    echo "Monats-Backup $MONAT angelegt"
fi

# ---- 4. Retention -----------------------------------------------------------
find "$BACKUP_DIR/daily" -name '*.gz' -mtime +"$RETENTION_DAILY" -delete
find "$BACKUP_DIR/monthly" -name '*.gz' -mtime +"$RETENTION_MONTHLY_TAGE" -delete

# ---- Zusammenfassung --------------------------------------------------------
echo "Backup OK ($HEUTE): $(du -h "$DB_DUMP" | cut -f1) DB, $(du -h "$UPLOADS_ARCHIV" | cut -f1) Uploads" \
     "| daily: $(find "$BACKUP_DIR/daily" -name '*.gz' | wc -l | tr -d ' ') Dateien," \
     "monthly: $(find "$BACKUP_DIR/monthly" -name '*.gz' | wc -l | tr -d ' ') Dateien"
