# VDSt Kassensystem

Kassenbuch- und Abrechnungssystem für den **Verein deutscher Studenten zu Erlangen**.

Bewusst klein gehalten: **ein** Kassenwart (Ehrenamt), ~12 Aktive, ein paar Belege pro Woche.
Keine Multi-User-Verwaltung, keine Rollen, kein Feature-Creep. Das Tool sammelt Belege,
führt ein Kassenbuch mit 3 Konten und erzeugt monatliche Abrechnungen für AH²-Bund und
Heimverein (HV) als Excel/ZIP.

---

## Funktionen

- **Kassenbuch** mit 3 Konten (Aktivenkasse, Getränkekasse, Barkasse) und Live-Kontostand
- **Beleg-Upload** (PDF/JPG/PNG, max. 10 MB) mit automatischer Belegnummer aus dem
  Rechnungsdatum (`YYYY-MM-DD-NNN`) und Ablage nach `uploads/belege/YYYY/MM/`
- **AH²- und HV-Abrechnungen** mit AJAX-Beleg-Zuordnung; HV zusätzlich mit Freitext-Begründung
- **Exporte** – pro Bereich genau zwei Formate: **Excel** und **Komplett-ZIP** (Excel + Beleg-Dateien)
- **Suche & Filter** über Beschreibung/Lieferant/Notizen, Datum, Kategorie, Status und Betrag
- **Master-Passwort-Login** mit 8-Stunden-Session (Idle-Timeout)

---

## Stack

- **CodeIgniter 4** (PHP 8.1+), **MySQL 8**
- **PhpOffice/PhpSpreadsheet** für die Excel-Exporte
- **Bootstrap 5** (CDN) + Vanilla JS – geteilte Logik in `public/js/app.js`, Rest inline in den Views
- **Docker** (`docker-compose`): App auf `:8080`, phpMyAdmin auf `:8081`

---

## Setup (Docker – empfohlen)

Auf dem Entwicklungsrechner gibt es **kein Host-PHP/Composer** – alles läuft im Container.

```bash
# 1. Umgebung anlegen
cp env .env
#    In .env mindestens setzen:
#      vdst.master_password = <starkes_passwort>
#      vdst.kassenwart_name = "Vorname Nachname"
#    Für Produktivbetrieb zusätzlich: CI_ENVIRONMENT = production

# 2. Container starten (App :8080, phpMyAdmin :8081, MySQL nur im Docker-Netz)
docker-compose up -d

# 3. Datenbank migrieren (legt Tabellen + Basisdaten an)
docker exec kassensystem-vdst-web php spark migrate
```

App danach unter <http://localhost:8080> erreichbar, phpMyAdmin unter <http://localhost:8081>.

Datenbank-Defaults (Container): DB `vdst_kassensystem_small`, User `kassenuser`.
Passwörter sind über Umgebungsvariablen überschreibbar (`DB_PASS`, `MYSQL_ROOT_PASSWORD`);
die Defaults gelten nur für lokale Entwicklung.

### Container-Befehle

```bash
docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/   # Tests (HealthTest, BetragTest)
docker exec kassensystem-vdst-web php spark migrate                # Migrationen
docker exec kassensystem-vdst-web php spark routes                 # Routenliste
docker exec kassensystem-vdst-web php -l <datei>                   # Syntax-Check
```

### Ohne Docker (Host mit PHP 8.1+ / Composer)

```bash
composer install
cp env .env          # DB-Zugang + vdst.master_password konfigurieren
php spark migrate
php spark serve       # Dev-Server auf :8080
```

---

## Architektur

### Controller (`app/Controllers/`)
- `DashboardController`, `BuchungenController`, `BelegeController`, `AuthController`
- `AbstractAbrechnungenController` mit den dünnen Subklassen
  `AhAbrechnungenController` / `HvAbrechnungenController` (nur `$typ`/`$typName`/Modell –
  die gesamte Logik liegt in der Basisklasse)

### Models (`app/Models/`)
`BelegModel`, `BuchungModel`, `AhAbrechnungModel`, `HvAbrechnungModel`,
`AbrechnungBelegModel` (Junction `abrechnung_belege` – einziger Codepfad für Beleg-Zuordnungen).

### Datenbank
```
belege               # Herzstück – alle Belege inkl. Datei
buchungen            # Kassenbuch-Einträge (optional mit beleg_id)
ah_abrechnungen      # AH²-Monatsabrechnungen
hv_abrechnungen      # HV-Abrechnungen (mit Freitext-Begründung)
abrechnung_belege    # Verknüpfung Abrechnung ↔ Beleg (M:N)
```
Gesamtsummen berechnet PHP (`berechneGesamtsumme()`) bei jeder Zuordnung – **keine DB-Trigger**.

### Geteilte Bausteine
- **Views** `app/Views/abrechnungen/*` werden von AH und HV geteilt (Steuerung über `$typ`)
- **Upload-Logik** zentral in `app/Libraries/BelegUpload.php`
- **Auth** ausschließlich über `App\Libraries\Auth::istAngemeldet()`
- **Labels/Formatierung** in `app/Helpers/label_helper.php` (autogeladen)
- **Exporte** über `app/Helpers/ExcelHelper.php` + `ZipHelper.php`

---

## Kernkonzepte

- **Belegnummern** `YYYY-MM-DD-NNN` aus dem Rechnungsdatum; der Dateiname entspricht der Belegnummer.
- **Beträge** DECIMAL(10,2); Eingaben werden mit `normalisiere_betrag()` normalisiert.
  Deutsches Komma ist Dezimaltrenner (`10,50` → `10.50`), reine Tausenderpunkte werden
  entfernt (`1.000` → `1000`). Regressionstest: `tests/unit/BetragTest.php`.
- **Beleg-Status** (ENUM): `erfasst → in_abrechnung → abgerechnet → bezahlt`. Nur `erfasst`
  ist editierbar.
- **Auth**: ein Master-Passwort aus `.env` (`vdst.master_password`), Session 8 h
  (`vdst.session_timeout`). Logout ist **POST** (Formular mit CSRF-Token), kein GET-Link.
- **CSRF** global aktiv (Token rotiert pro POST); AJAX-Antworten liefern `csrf_hash` mit zurück.
- **Upload-Pfade** sind relativ zu `public/` (`uploads/belege/YYYY/MM/`) – nie `public/`
  voranstellen.

---

## Nutzung

1. **Anmelden** unter `/auth/login` mit dem Master-Passwort aus der `.env`.
2. **Belege** unter `/belege` erfassen (Upload + Kategorie: normal / AH²-berechtigt / HV-berechtigt).
3. **Kassenbuch** unter `/buchungen` führen (3 Konten, optionale Beleg-Verknüpfung, Excel/ZIP-Export).
4. **Abrechnungen** unter `/abrechnungen/ah` bzw. `/abrechnungen/hv`: Monatsabrechnung anlegen,
   berechtigte Belege per AJAX zuordnen, als Excel oder ZIP für die Einreichung exportieren.

### AJAX-Endpoints (Abrechnungen)
```
POST /abrechnungen/{typ}/addBeleg/{id}      # Beleg zuordnen
POST /abrechnungen/{typ}/removeBeleg/{id}   # Beleg entfernen
POST /abrechnungen/{typ}/changeStatus/{id}  # Status ändern
```
Antwortformat:
```json
{ "success": true, "message": "…", "neue_gesamtsumme": "1.234,56 €", "csrf_hash": "…" }
```

---

## Entwicklung

- **Main-Branch ist `small`** (nicht `main`). Für Änderungen einen Feature-Branch anlegen.
- PRs gegen `small` (`gh pr create --base small`); Merge lokal per `git merge --no-ff` + `git push`.
- Tests: `docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/`.
- Detaillierte Konventionen und Invarianten stehen in [`CLAUDE.md`](CLAUDE.md).

### Bewusst entfernt – nicht wieder einbauen
Multi-User/Rollen, Session-Timeout-Warnsystem mit Auto-Refresh, Keyboard-Shortcuts,
automatische HV-Begründungs-Generatoren, Dashboard-Quick-Upload, drittes Exportformat
(Buchungen-Listen-Excel), DB-Trigger/-Views, Tabelle `system_einstellungen`.

---

## Wartung

```bash
# DB-Backup (aus dem DB-Container)
docker exec kassensystem-vdst-db mysqldump -u kassenuser -p vdst_kassensystem_small > backup_$(date +%Y%m%d).sql

# Upload-Ordner sichern
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz public/uploads/

# Temporäre ZIP-Dateien aufräumen
find writable/temp/zip/ -name "*.zip" -mtime +1 -delete
```

Vor jedem Deploy mit `php spark migrate`: DB-Dump ziehen und `public/uploads/` kopieren.

---

## Lizenz

Siehe [`LICENSE`](LICENSE).
