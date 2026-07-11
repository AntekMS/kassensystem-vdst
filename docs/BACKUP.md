# Backup & Recovery

Backup-Strategie für das VDSt-Kassensystem (Issue #15). Die Skripte setzen das
**Docker-Setup** voraus (Container `kassensystem-vdst-db` / `-web`) und laufen auf
dem **Host** — kein Host-PHP nötig, `mysqldump`/`mysql` werden per `docker exec`
im DB-Container ausgeführt (der Web-Container hat keinen MySQL-Client).
Für den Ohne-Docker-Betrieb (XAMPP) gelten sie nicht.

## Was gesichert wird

| Was | Wie | Warum |
|---|---|---|
| MySQL-Datenbank | `mysqldump --single-transaction --no-tablespaces --routines --triggers`, gzip | Kassenbuch, Belege-Metadaten, Schulden, Abrechnungen |
| Beleg-Dateien | `tar -czf` von `public/uploads/` (Bind-Mount, direkt vom Host) | Original-Belege (PDF/JPG/PNG) |

`--no-tablespaces` ist Absicht: ohne die Option bräuchte `kassenuser` auf MySQL 8
das PROCESS-Privileg.

## Ablageschema

```
backups/
├── daily/          db_YYYY-MM-DD.sql.gz + uploads_YYYY-MM-DD.tar.gz   (30 Tage)
├── monthly/        db_YYYY-MM.sql.gz   + uploads_YYYY-MM.tar.gz      (~12 Monate)
└── pre-restore/    Safety-Dumps, die restore.sh vor jedem Einspielen anlegt
```

Die Monats-Promotion ist selbstheilend: der **erste** Backup-Lauf eines Monats
kopiert seine Dateien nach `monthly/` — egal an welchem Tag er läuft. Die
monatliche Retention läuft über `-mtime +366` und ist damit approximativ
(kurzzeitig können 13 Monats-Backups liegen).

## Konfiguration

Alles per Umgebungsvariablen, Defaults passen zum lokalen `docker-compose`-Setup:

| Variable | Default | Hinweis |
|---|---|---|
| `BACKUP_DIR` | `<repo>/backups` | Produktiv besser auf eine **andere Platte** legen |
| `DB_CONTAINER` | `kassensystem-vdst-db` | |
| `DB_NAME` | `vdst_kassensystem_small` | |
| `DB_USER` | `kassenuser` | |
| `DB_PASS` | `kassenpass123` | **Produktiv zwingend setzen** (Wert aus docker-compose/Umgebung) |
| `RETENTION_DAILY` | `30` | Tage |
| `RETENTION_MONTHLY_TAGE` | `366` | Tage |

## Manuell ausführen

```bash
./scripts/backup.sh                       # lokales Dev-Setup
DB_PASS=geheim ./scripts/backup.sh        # Produktion
```

Exit-Code ≠ 0 und eine `FEHLER:`-Zeile auf stderr, wenn etwas schiefgeht
(Container läuft nicht, Dump leer/unvollständig, Archiv defekt).

## Cron einrichten (täglich 02:00)

```cron
MAILTO=kassenwart@example.org
0 2 * * * DB_PASS=geheim /pfad/zu/kassensystem-vdst/scripts/backup.sh >> /var/log/kassensystem-backup.log 2>&1
```

`MAILTO` sorgt dafür, dass Cron bei Fehlern (stderr-Ausgabe) eine Mail schickt —
mehr Monitoring braucht dieses System nicht. Hinweis: `DB_PASS` steht damit in
der Crontab; bei diesem Maßstab (ein Kassenwart, ein Server) ist das akzeptabel.

## Wiederherstellung

### Komplett-Ausfall (Runbook)

1. Frisches Clone + Container starten:
   ```bash
   git clone https://github.com/AntekMS/kassensystem-vdst.git && cd kassensystem-vdst
   cp env .env   # anpassen: CI_ENVIRONMENT, baseURL, vdst.master_password
   docker-compose up -d
   # warten bis MySQL bereit ist: docker logs -f kassensystem-vdst-db
   ```
2. Backup einspielen (DB + Uploads):
   ```bash
   ./scripts/restore.sh backups/daily/db_2026-07-11.sql.gz backups/daily/uploads_2026-07-11.tar.gz
   ```
   Das Skript fragt nach Bestätigung (`JA`), legt vorher einen Safety-Dump nach
   `backups/pre-restore/` und legt den alten Upload-Stand als
   `public/uploads.pre-restore.<zeitstempel>` beiseite.
3. Migrationen nachziehen (falls der Dump älter als der Code ist):
   ```bash
   docker exec kassensystem-vdst-web php spark migrate
   ```
4. Smoke-Test: `http://localhost:8080` → Login → Dashboard, ein Beleg-PDF öffnen.

**RTO/RPO:** Mit täglichen Dumps sind die Ziele aus Issue #15 erfüllt —
Wiederherstellung dauert deutlich unter 4 h (RTO), maximaler Datenverlust
ist ein Tag (RPO 24 h).

### Einzelnen Beleg wiederherstellen

Beleg-Dateien liegen im Archiv unter `uploads/belege/YYYY/MM/<belegnummer>.<ext>`:

```bash
tar -tzf backups/daily/uploads_2026-07-11.tar.gz | grep 2026-07-08   # suchen
tar -xzf backups/daily/uploads_2026-07-11.tar.gz -C public uploads/belege/2026/07/2026-07-08-001.pdf
```

### Test-Restore ohne Produktivsystem

Dump in einen Wegwerf-Container einspielen und prüfen:

```bash
docker run --rm -d --name restore-test -e MYSQL_ROOT_PASSWORD=test -e MYSQL_DATABASE=testdb mysql:8.0
# warten bis bereit (docker logs restore-test), dann:
gunzip -c backups/daily/db_2026-07-11.sql.gz | docker exec -i -e MYSQL_PWD=test restore-test mysql -u root testdb
docker exec -e MYSQL_PWD=test restore-test mysql -u root testdb -e "SHOW TABLES; SELECT COUNT(*) FROM belege;"
docker stop restore-test
```

## Bewusst nicht umgesetzt

- **Cloud-Ziele (S3/Google Drive):** Statt eigener Integration den Backup-Ordner
  per `rsync`/`rclone` offsite spiegeln, z. B.
  `rclone sync backups/ gdrive:kassensystem-backups`.
- **Point-in-Time-Recovery über Binlogs:** Tägliche Dumps reichen für diesen
  Maßstab (RPO 24 h ist akzeptiert).
- **Monitoring-Stack:** Cron-`MAILTO` + Exit-Code genügen.

**Wichtig:** `backups/` auf derselben Platte schützt nur vor Nutzerfehlern,
nicht vor Platten-/Serverausfall — den Ordner regelmäßig auf ein externes
Ziel kopieren (externe Platte, anderer Rechner, Cloud via rclone).
