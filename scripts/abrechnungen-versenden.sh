#!/usr/bin/env bash
#
# Automatischer Monats-Versand der Abrechnungen (Issue #37):
# Schickt die offene AH- und HV-Abrechnung als ZIP (Excel + Belege) per E-Mail
# an den Kassenwart (vdst.kassenwart_email). Der Status bleibt unverändert.
#
# Läuft auf dem Host (kein Host-PHP nötig) und treibt `php spark` im Web-Container.
# Ohne email.*/vdst.kassenwart_email in .env passiert nichts (sauberer Abbruch).
#
# Cron-Beispiel (am Monatsersten 08:00):
#   0 8 1 * * /pfad/zu/kassensystem-vdst/scripts/abrechnungen-versenden.sh >> /var/log/kassensystem-abrechnungen.log 2>&1

set -euo pipefail

WEB_CONTAINER="${WEB_CONTAINER:-kassensystem-vdst-web}"

fehler() {
    echo "FEHLER: $*" >&2
    exit 1
}

command -v docker >/dev/null || fehler "docker nicht gefunden"
docker ps --format '{{.Names}}' | grep -qx "$WEB_CONTAINER" \
    || fehler "Web-Container '$WEB_CONTAINER' läuft nicht"

echo "[$(date +%Y-%m-%dT%H:%M:%S)] abrechnungen:versenden startet"
docker exec "$WEB_CONTAINER" php spark abrechnungen:versenden
