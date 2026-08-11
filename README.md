# VDSt Kassensystem

Kassenbuch- und Abrechnungssystem für den **Verein deutscher Studenten zu Erlangen**.

Bewusst klein gehalten: **ein** Kassenwart (Ehrenamt), ~12 Aktive, ein paar Belege pro Woche.
Keine Multi-User-Verwaltung, keine Rollen, kein Feature-Creep. Das Tool sammelt Belege,
führt ein Kassenbuch mit 3 Konten und erzeugt monatliche Abrechnungen für AH²-Bund und
Heimverein (HV) als Excel, ZIP und PDF.

---

## Funktionen

- **Kassenbuch** mit 3 Konten (Aktivenkasse, Getränkekasse, Barkasse) und Live-Kontostand
- **Beleg-Upload** (PDF/JPG/PNG, max. 10 MB) mit automatischer Belegnummer aus dem
  Rechnungsdatum (`YYYY-MM-DD-NNN`) und Ablage nach `uploads/belege/YYYY/MM/`
- **AH²- und HV-Abrechnungen** mit AJAX-Beleg-Zuordnung; HV zusätzlich mit Freitext-Begründung.
  Per 1 Klick lassen sich alle verfügbaren Belege übernehmen („Alle hinzufügen" bzw.
  Checkbox beim Erstellen)
- **Exporte** – Grundregel: pro Bereich **Excel** und **Komplett-ZIP** (Excel + Beleg-Dateien).
  Zwei bewusste Ergänzungen: Abrechnungen haben zusätzlich eine **PDF-Rechnung** (VDSt-gebrandet,
  ersetzt Excel/ZIP nicht), und die Inventur hat statt des ZIPs einen **PDF-Export**
- **Schuldenliste** – Forderungen/Verbindlichkeiten pro Person (Freitext-Name)
  mit nachvollziehbarer Historie (z.B. monatliche Getränkerechnungen, Rückzahlungen als
  negativer Betrag) und Getränkestopp-Badge ab 50 € Getränkeschulden; mit Namenssuche,
  Status-Filter (offene Getränke/Forderungen/Verbindlichkeiten, Getränkestopp,
  ausgeglichen) und Sortierung
- **Automatische Schulden-Einträge** – Beleg mit „Erstattung an" legt die
  Verbindlichkeit an, eingereichte Abrechnungen erscheinen als Forderung gegen
  AH²-Bund/Heimverein (bezahlt = ausgeglichen), und Buchungen können Schulden
  direkt ausgleichen; solche Einträge werden über ihre Quelle gepflegt
- **Getränkerechnung-Import** – die monatliche Excel des Getränkewarts hochladen:
  Namen und Beträge werden automatisch erkannt (Vorschau vor dem Anlegen), pro
  Person entsteht eine Getränke-Forderung; die Coleur-/Bund-Summen werden als
  VDSt-gebrandete **PDF-Rechnungen** in die offene AH-Abrechnung übernommen
- **Rechnungsversand** – nach dem Import lassen sich personalisierte
  PDF-Einzelrechnungen per E-Mail an die Aktiven verschicken (Adressen im
  Personen-Register, Versand-Log gegen Doppelversand) und eine
  Übersichts-Rechnung als PDF für den Aushang herunterladen
- **Allgemeine Rechnung** – Rechnung über beliebige offene Forderungen einer Person
  (von der einzelnen Spende bis „alle offenen Schulden") inklusive der
  **Überweisungsdetails** des Vereins; als Vorschau im Browser oder per E-Mail an die
  im Register hinterlegte Adresse
- **Personen-Register** – Stammdaten der Aktiven (Vor-/Nachname, E-Mail) unter
  „Personen"; Grundlage für Schulden-Zuordnung und Rechnungsversand
- **Inventur** – eigene Seite ("Kassenwart – Aktueller Bestand": Kassenbestand +
  Forderungen − Verbindlichkeiten) mit Dashboard-Kachel, Excel- und PDF-Download
- **Muster/Vorlagen** – die Seite `/muster` führt jedes vom System erzeugte Dokument
  (7 PDFs, 4 Excel) mit fiktiven Platzhalterdaten vor, ohne echte Daten anzufassen –
  praktisch zum Nachschauen, wie eine Rechnung beim Empfänger ankommt
- **Suche & Filter** über Beschreibung/Lieferant/Notizen, Datum, Kategorie, Status und Betrag
- **Darkmode** – umschaltbar über Sidebar bzw. Topbar, folgt beim ersten Aufruf der
  System-Einstellung und merkt sich danach die Wahl
- **Master-Passwort-Login** mit 8-Stunden-Session (Idle-Timeout)

---

## Stack

- **CodeIgniter 4** (PHP 8.1+), **MySQL 8**
- **PhpOffice/PhpSpreadsheet** für die Excel-Exporte, **dompdf** für die PDF-Rechnungen
- **Bootstrap 5 + Bootstrap Icons** (CDN) + Vanilla JS – geteilte Logik in `public/js/app.js`,
  zentrales Design-System in `public/css/app.css` (Sidebar-Layout, Vereinsfarben Schwarz/Weiß/Rot
  als Akzente, mobile Karten-Stapelung der Tabellen)
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
docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/   # Tests (Health, Betrag, Labels, Import, Personen, PDF, Versand)
docker exec kassensystem-vdst-web php spark migrate                # Migrationen
docker exec kassensystem-vdst-web php spark routes                 # Routenliste
docker exec kassensystem-vdst-web php spark abrechnungen:versenden # Abrechnungen an Kassenwart mailen (Issue #37)
docker exec kassensystem-vdst-web php -l <datei>                   # Syntax-Check
```

### E-Mail-Versand einrichten (optional)

Für den Rechnungsversand – Einzelrechnungen nach dem Getränkerechnung-Import wie auch
die allgemeine Rechnung über offene Forderungen – braucht die `.env` einen SMTP-Zugang
(Beispielblock steht in der Datei `env`):

```
email.protocol  = smtp
email.SMTPHost  = mail.example.org
email.SMTPUser  = kassenwart@example.org
email.SMTPPass  = geheim
email.SMTPPort  = 587
email.SMTPCrypto = tls
email.fromEmail = kassenwart@example.org
email.fromName  = 'VDSt Kassenwart'
```

Ohne diese Keys bleibt der Versand-Button deaktiviert – Import, Übersichts-PDF
und Adress-Verwaltung funktionieren trotzdem.

**Bankverbindung für die Überweisungsdetails:** Die Keys `vdst.bank_iban`,
`vdst.bank_kontoinhaber`, `vdst.bank_bic` und `vdst.bank_name` (auskommentierte
Beispiele in `env`) speisen den Überweisungs-Absatz im Rechnungs-Mailtext und den
Überweisungs-Block auf der allgemeinen Rechnung. Ohne `vdst.bank_iban` entfallen
beide; einzelne leere Werte lassen nur ihre Zeile weg.

**Automatischer Monats-Versand der Abrechnungen (Issue #37):** Setzt man zusätzlich
`vdst.kassenwart_email` in der `.env`, schickt `php spark abrechnungen:versenden`
die je offene AH- und HV-Abrechnung als ZIP (Excel + Belege) an diese Adresse (der
Status bleibt unverändert). Für den monatlichen Lauf den Host-Wrapper per Cron
eintragen (Muster wie `scripts/backup.sh`):

```
0 8 1 * * /pfad/zu/kassensystem-vdst/scripts/abrechnungen-versenden.sh >> /var/log/kassensystem-abrechnungen.log 2>&1
```

Zum lokalen Testen ohne echten SMTP-Server eignet sich
[Mailpit](https://mailpit.axllent.org):

```bash
docker run -d --name mailpit --network kassensystem-vdst_kassensystem-network -p 8025:8025 axllent/mailpit
# .env: email.protocol=smtp, email.SMTPHost=mailpit, email.SMTPPort=1025,
#       email.SMTPCrypto= (leer), email.fromEmail=test@vdst.local
# Mails ansehen: http://localhost:8025
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
- `DashboardController`, `BuchungenController`, `BelegeController`, `SchuldenController`,
  `AuthController`, `MusterController` (Vorlagen-Seite mit Platzhalterdaten)
- `AbstractAbrechnungenController` mit den dünnen Subklassen
  `AhAbrechnungenController` / `HvAbrechnungenController` (nur `$typ`/`$typName`/Modell –
  die gesamte Logik liegt in der Basisklasse)

### Commands (`app/Commands/`)
`AbrechnungenVersenden` (`php spark abrechnungen:versenden`) – Monats-Versand der offenen
Abrechnungen an den Kassenwart, für den Host-Cron.

### Models (`app/Models/`)
`BelegModel`, `BuchungModel`, `SchuldModel`,
`AbstractAbrechnungModel` mit den dünnen Subklassen `AhAbrechnungModel` / `HvAbrechnungModel`
(nur Tabelle, Typ, Label und Beleg-Kategorie – die gesamte Logik liegt in der Basisklasse),
`AbrechnungBelegModel` (Junction `abrechnung_belege` – einziger Codepfad für Beleg-Zuordnungen),
`PersonModel` (Personen-Register: Vor-/Nachname + E-Mail, autoritative Namensquelle),
`SchuldPositionModel` (Getränke-Einzelpositionen je Import-Forderung),
`GetraenkeVersandModel` (Versand-Log).

### Datenbank
```
belege               # Herzstück – alle Belege inkl. Datei
buchungen            # Kassenbuch-Einträge (optional mit beleg_id)
schulden             # Schulden-Ledger pro Person (Name-String + Soft-Link person_id, Rückzahlung = negativ)
schuld_positionen    # Getränke-Einzelpositionen je Import-Forderung (Menge × Einzelpreis)
persons              # Personen-Register (Vor-/Nachname, E-Mail) – autoritative Namensquelle
ah_abrechnungen      # AH²-Monatsabrechnungen
hv_abrechnungen      # HV-Abrechnungen (mit Freitext-Begründung)
abrechnung_belege    # Verknüpfung Abrechnung ↔ Beleg (M:N)
getraenke_versand    # Log der verschickten Getränkerechnungen
```
Gesamtsummen berechnet PHP (`berechneGesamtsumme()`) bei jeder Zuordnung – **keine DB-Trigger**.

### Geteilte Bausteine
- **Views** `app/Views/abrechnungen/*` werden von AH und HV geteilt (Steuerung über `$typ`)
- **Upload-Logik** zentral in `app/Libraries/BelegUpload.php`
- **Auth** ausschließlich über `App\Libraries\Auth::istAngemeldet()`
- **Labels/Formatierung** in `app/Helpers/label_helper.php` (autogeladen)
- **Exporte** über `app/Helpers/ExcelHelper.php` + `ZipHelper.php`; das Abrechnungs-Excel
  baut für AH und HV dieselbe Methode `ExcelHelper::erstelleAbrechnung(…, $typ)`
- **PDF-Rechnungen** über `app/Libraries/RechnungPdf.php` (dompdf) – ein geteiltes
  Template `app/Views/pdf/rechnung.php`, gesteuert über `$typ`
- **Theme** zentral in `public/css/app.css`; Darkmode über Bootstraps `data-bs-theme`

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
   berechtigte Belege per AJAX zuordnen, als Excel, ZIP oder PDF-Rechnung exportieren.
5. **Getränkerechnung** unter `/schulden/import` hochladen: Vorschau prüfen und bestätigen –
   Forderungen und die Coleur-/Bund-PDF-Belege entstehen automatisch. Danach auf der
   Versand-Seite E-Mail-Adressen ergänzen und die Einzelrechnungen verschicken bzw. das
   Übersichts-PDF für den Aushang herunterladen.
6. **Rechnung über offene Forderungen** auf der Personen-Seite (`/schulden/person?name=…`):
   Gesamtrechnung ansehen oder direkt per E-Mail verschicken; einzelne Forderungen haben
   je einen eigenen Rechnungs-Link.
7. **Inventur** unter `/inventur` einsehen und als Excel oder PDF herunterladen.
8. **Muster** unter `/muster` – jedes erzeugte Dokument mit Platzhalterdaten ansehen.

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
# Backup (DB-Dump + Beleg-Dateien, Retention 30 Tage/12 Monate) — läuft auf dem Host
./scripts/backup.sh

# Wiederherstellung (mit Sicherheitsabfrage und Safety-Dump)
./scripts/restore.sh backups/daily/db_YYYY-MM-DD.sql.gz [backups/daily/uploads_YYYY-MM-DD.tar.gz]

# Temporäre ZIP-Dateien aufräumen
find writable/temp/zip/ -name "*.zip" -mtime +1 -delete
```

Details (Cron-Einrichtung, Recovery-Runbook, Test-Restore): [`docs/BACKUP.md`](docs/BACKUP.md).

Vor jedem Deploy mit `php spark migrate`: DB-Dump ziehen und `public/uploads/` kopieren
(`./scripts/backup.sh` erledigt beides).

---

## Lizenz

Siehe [`LICENSE`](LICENSE).
