#!/usr/bin/env bash
#
# Wiederherstellung des VDSt-Kassensystems aus einem Backup (Docker-Setup).
#
#   ./scripts/restore.sh <db_dump.sql.gz> [uploads.tar.gz] [--force]
#
# Ablauf:
#   1. Sicherheitsabfrage (Eingabe "JA"; --force überspringt sie)
#   2. Safety-Dump der AKTUELLEN Datenbank nach backups/pre-restore/
#   3. DB-Dump einspielen
#   4. Optional: Upload-Dateien wiederherstellen (alter Stand wird beiseitegelegt)
#
# Danach ggf.: docker exec kassensystem-vdst-web php spark migrate
# Doku: docs/BACKUP.md

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

BACKUP_DIR="${BACKUP_DIR:-$REPO_DIR/backups}"
DB_CONTAINER="${DB_CONTAINER:-kassensystem-vdst-db}"
DB_NAME="${DB_NAME:-vdst_kassensystem_small}"
DB_USER="${DB_USER:-kassenuser}"
DB_PASS="${DB_PASS:-kassenpass123}"

fehler() {
    echo "FEHLER: $*" >&2
    exit 1
}

DB_DUMP=""
UPLOADS_ARCHIV=""
FORCE=0
for arg in "$@"; do
    case "$arg" in
        --force) FORCE=1 ;;
        *.sql.gz) DB_DUMP="$arg" ;;
        *.tar.gz) UPLOADS_ARCHIV="$arg" ;;
        *) fehler "Unbekanntes Argument: $arg (erwartet: <db_dump.sql.gz> [uploads.tar.gz] [--force])" ;;
    esac
done

[ -n "$DB_DUMP" ] || fehler "Kein DB-Dump angegeben. Aufruf: $0 <db_dump.sql.gz> [uploads.tar.gz] [--force]"
[ -f "$DB_DUMP" ] || fehler "DB-Dump $DB_DUMP nicht gefunden"
gzip -t "$DB_DUMP" || fehler "DB-Dump $DB_DUMP ist kein gültiges gzip"
if [ -n "$UPLOADS_ARCHIV" ]; then
    [ -f "$UPLOADS_ARCHIV" ] || fehler "Upload-Archiv $UPLOADS_ARCHIV nicht gefunden"
    gzip -t "$UPLOADS_ARCHIV" || fehler "Upload-Archiv $UPLOADS_ARCHIV ist kein gültiges gzip"
fi

docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER" \
    || fehler "DB-Container '$DB_CONTAINER' läuft nicht (docker-compose up -d)"

# ---- 1. Sicherheitsabfrage --------------------------------------------------
if [ "$FORCE" -ne 1 ]; then
    echo "ACHTUNG: Datenbank '$DB_NAME' wird mit $DB_DUMP ÜBERSCHRIEBEN."
    [ -n "$UPLOADS_ARCHIV" ] && echo "         public/uploads/ wird durch $UPLOADS_ARCHIV ersetzt."
    read -r -p "Zum Fortfahren JA eingeben: " ANTWORT
    [ "$ANTWORT" = "JA" ] || { echo "Abgebrochen."; exit 0; }
fi

# ---- 2. Safety-Dump ---------------------------------------------------------
ZEITSTEMPEL="$(date +%Y-%m-%d_%H%M%S)"
mkdir -p "$BACKUP_DIR/pre-restore"
SAFETY_DUMP="$BACKUP_DIR/pre-restore/db_$ZEITSTEMPEL.sql.gz"

docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" \
    mysqldump --single-transaction --no-tablespaces --routines --triggers \
    -u "$DB_USER" "$DB_NAME" | gzip > "$SAFETY_DUMP"
[ -s "$SAFETY_DUMP" ] || fehler "Safety-Dump fehlgeschlagen — Abbruch VOR der Wiederherstellung"
echo "Safety-Dump: $SAFETY_DUMP"

# ---- 3. DB einspielen -------------------------------------------------------
gunzip -c "$DB_DUMP" | docker exec -i -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" \
    mysql -u "$DB_USER" "$DB_NAME"
echo "Datenbank wiederhergestellt aus $DB_DUMP"

# ---- 4. Uploads einspielen --------------------------------------------------
if [ -n "$UPLOADS_ARCHIV" ]; then
    if [ -d "$REPO_DIR/public/uploads" ]; then
        mv "$REPO_DIR/public/uploads" "$REPO_DIR/public/uploads.pre-restore.$ZEITSTEMPEL"
        echo "Alter Upload-Stand: public/uploads.pre-restore.$ZEITSTEMPEL"
    fi
    tar -xzf "$UPLOADS_ARCHIV" -C "$REPO_DIR/public"
    echo "Uploads wiederhergestellt aus $UPLOADS_ARCHIV"
fi

echo
echo "Fertig. Falls der Dump älter als der Code ist, noch Migrationen ausführen:"
echo "  docker exec kassensystem-vdst-web php spark migrate"
