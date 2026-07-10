# VDSt Kassensystem

## Zweck & Maßstab
Kassenbuch- und Abrechnungssystem für den Verein deutscher Studenten zu Erlangen.
**Ein** Kassenwart (Ehrenamt), ~12 Aktive, maximal ein paar Belege pro Woche. Das Tool ist
bewusst klein: keine Multi-User-Verwaltung, keine Rollen, kein Enterprise-Feature-Creep.
Aufgaben: Belege sammeln, Kassenbuch mit 3 Konten führen, monatliche Abrechnungen
an AH²-Bund und Heimverein (HV) als Excel/ZIP einreichen, Schulden der Aktiven
nachhalten (Schuldenliste + Inventur-Export).

## Stack
- CodeIgniter 4 (PHP 8.1+), MySQL 8
- PhpOffice/PhpSpreadsheet für Excel-Exporte
- Bootstrap 5 via CDN, geteiltes JS in `public/js/app.js`, Rest inline in den Views
- Docker-Setup (`docker-compose up -d` → App auf :8080, phpMyAdmin auf :8081)
- Lokal alternativ: `php spark serve` + MySQL (XAMPP-Default in `app/Config/Database.php`)

## Befehle
Auf diesem Rechner gibt es **kein Host-PHP/Composer** — alles im laufenden Web-Container
ausführen (`docker ps` → `kassensystem-vdst-web`, `-db`, `-phpmyadmin`):
- `docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/` — Tests
  (`HealthTest`, `BetragTest`, `SchuldLabelTest`); entspricht `composer test`
- `docker exec kassensystem-vdst-web php spark migrate` — Migrationen (auch die
  Datei-/Trigger-Cleanup-Migrationen)
- `docker exec kassensystem-vdst-web php spark routes` — Routenliste
- `docker exec kassensystem-vdst-web php -l <datei>` — Syntax-Check einzelner Dateien
- Smoke-Test/Login-Flow: `curl` gegen `http://localhost/...` **im Container** (CSRF-Token
  aus der Login-Seite lesen, `vdst.master_password` aus `.env`).

## Workflow
- Main-Branch ist **`small`** (nicht `main`). Für Änderungen Feature-Branch anlegen.
- Der GitHub-Token darf **nicht per API mergen** — PR erstellen (`gh pr create --base small`),
  dann lokal `git merge --no-ff` in `small` + `git push`; `Closes #N` im PR-Body schließt
  das Issue beim Merge automatisch.
- Issues: Labels `priority: hoch|mittel|niedrig`, `security`, `tech-debt`, `Big Update`.
- **Doku aktuell halten**: Nach **jeder** abgeschlossenen Aufgabe diese `CLAUDE.md` (und
  bei Bedarf die `README.md`) auf den neuesten Stand bringen — geänderte Architektur,
  neue/entfernte Invarianten, Konventionen und Befehle einpflegen, damit die Landkarte
  nie vom Code abweicht. Gehört zur Aufgabe, nicht als optionaler Zusatz.

## Architektur-Landkarte
- **Controller** (`app/Controllers/`): `DashboardController`, `BelegeController`,
  `BuchungenController`, `SchuldenController`, `AuthController` sowie
  `AbstractAbrechnungenController` mit den dünnen Subklassen
  `AhAbrechnungenController`/`HvAbrechnungenController`
  (nur `$typ`, `$typName`, Modell — die ganze Logik liegt in der Basisklasse).
- **Models**: `BelegModel` (belege), `BuchungModel` (buchungen),
  `SchuldModel` (schulden), `AhAbrechnungModel`/`HvAbrechnungModel`
  (ah_/hv_abrechnungen), `AbrechnungBelegModel` (Junction abrechnung_belege —
  einziger Codepfad für Beleg-Zuordnungen).
- **Gemeinsame Views**: `app/Views/abrechnungen/*` werden von AH und HV geteilt,
  gesteuert über `$typ` ('ah'|'hv'). HV hat zusätzlich ein Freitext-Feld `begruendung`.
- **Upload-Logik**: zentral in `app/Libraries/BelegUpload.php` (genutzt von
  BelegeController::store und BuchungenController::store).
- **Auth**: `App\Libraries\Auth::istAngemeldet()` ist die EINZIGE Login-/Timeout-Prüfung;
  `AuthController::isAuthenticated()`, `AuthFilter` und der 404-Override in `Routes.php`
  delegieren dorthin. Logik nicht erneut duplizieren.
- **Labels**: `app/Helpers/label_helper.php` (autogeladen) — `kategorie_label()`,
  `beleg_status_label()`, `abrechnung_status_label()`, `konto_label()`,
  `schuld_typ_label()`, `schuld_kategorie_label()` (je mit `_optionen()`-Pendant),
  `formatiere_betrag()`, `normalisiere_betrag()`, `schaetze_archiv_groesse()`.
- **Geteiltes JS** (`public/js/app.js`, in `layouts/main.php` eingebunden):
  `confirmDelete()`, `showMessage()`, Export-Toasts; Views binden Verhalten per CSS-Klasse
  `js-autosubmit` (Filter-Selects) bzw. `js-betrag-format` (Betrag-Eingaben) — solche
  Handler NICHT wieder inline in Views duplizieren.
- **Exporte**: `app/Helpers/ExcelHelper.php` + `ZipHelper.php`. Pro Bereich genau
  2 Formate: Excel und Komplett-ZIP (Excel + Beleg-Dateien). Ausnahme Schulden:
  nur ein Export — die Inventur (`ExcelHelper::erstelleInventur`, ein Sheet
  "Kassenwart – Aktueller Bestand": Kassenbestand + Forderungen − Verbindlichkeiten).
- **Schulden** (`SchuldenController`/`SchuldModel`, Views `app/Views/schulden/*`):
  Ledger pro Person, Person ist **Freitext** (keine Personen-Tabelle; Datalist-Vorschläge,
  Namen werden getrimmt, Gruppierung case-insensitiv über die DB-Kollation).
  Personen-Detail läuft über `/schulden/person?name=…` (GET-Param wegen
  Leerzeichen/Umlauten, kein URI-Segment).

## Konventionen & Invarianten
- **Upload-Pfade** sind relativ zu FCPATH (= `public/`): `uploads/belege/YYYY/MM/`.
  NIE `public/` voranstellen — das war ein historischer Bug (Dateien in
  `public/public/…`), den `2026-07-08-000001_FixUploadPfade` migriert hat.
- **Belegnummern**: `YYYY-MM-DD-NNN` aus dem Rechnungsdatum; Dateiname = Belegnummer.
- **Beträge**: DECIMAL(10,2) in der DB; Eingaben werden vor der Validierung mit
  `normalisiere_betrag()` normalisiert. Deutsches Komma = Dezimaltrenner (`10,50`→`10.50`);
  reine Tausenderpunkt-Gruppen werden entfernt (`1.000`→`1000`), einzelner Punkt bleibt
  Dezimaltrenner (`10.50`, `1.5`). Regressionstest: `tests/unit/BetragTest.php`.
- **Beleg-Upload** (`BelegUpload::speichereBeleg`): DB-Insert ZUERST, Datei-Move DANACH,
  Nummernvergabe+Insert mit Retry gegen die Race Condition — bei Move-Fehler wird der
  Insert per `delete()` zurückgenommen. Reihenfolge nicht umdrehen.
- **Beleg-Status** (ENUM): `erfasst → in_abrechnung → abgerechnet → bezahlt`. Nur `erfasst`
  ist editierbar. `addBeleg`/`removeBeleg` verweigern `eingereicht`/`bezahlt`-Abrechnungen;
  `aendereStatus` setzt Belege beim Zurückstufen aus `bezahlt` wieder auf `in_abrechnung`
  (in beiden Modellen `Ah`/`HvAbrechnungModel` identisch pflegen).
- **Gesamtsummen** der Abrechnungen berechnet PHP (`berechneGesamtsumme()`) bei jedem
  Hinzufügen/Entfernen — es gibt KEINE DB-Trigger mehr (per Migration entfernt).
- **Schulden-Vorzeichen**: Beträge normal positiv, **Rückzahlungen negativ** (grün
  dargestellt); offener Stand = einfache `SUM(betrag)`. `typ` trennt Forderung
  (Person schuldet Verein) und Verbindlichkeit (Verein schuldet Person) — die beiden
  werden NIE gegeneinander verrechnet. Betrag 0 lehnt der Controller ab. Ab
  `GETRAENKESTOPP_LIMIT` (50 €, `Config/Constants.php`) Getränke-Forderungen zeigt
  die UI ein Getränkestopp-Badge.
- **Auth**: ein Master-Passwort aus `.env` (`vdst.master_password`), Session 8h
  (`Config/Session.php::$expiration` muss zu `vdst.session_timeout` passen).
  Auth-Filter kommt ausschließlich aus der Routen-Gruppe in `Routes.php`; `AuthFilter`
  matcht Public-Pfade exakt (`auth/login`, `auth/authenticate`). Logout ist **POST**
  (Formular mit `csrf_field()`), kein GET-Link.
- **Excel-Download**: `ExcelHelper::downloadExcel()` gibt eine CI4-Download-Response zurück
  (schreibt vorher in Temp-Datei, damit Fehler VOR der Ausgabe im try/catch landen). Aufrufer
  müssen sie `return`en — kein rohes `header()`/`exit`. Temp-Dateien immer `uniqid()`, nie `time()`.
- **404-Override** (`Routes.php`): CI4 `echo`t den Closure-Rückgabewert → NIE ein Response-/
  RedirectResponse-Objekt zurückgeben (String-Cast crasht), stattdessen die geteilte
  `service('response')` mutieren und `''` zurückgeben.
- **CSRF** ist global aktiv (Token rotiert pro POST). AJAX-Antworten der
  Abrechnungs-Endpoints liefern immer `csrf_hash` zurück; das JS in
  `select_belege.php` aktualisiert den Token daraus (defensiv geparst, bei 403 Reload).
- **Ausgaben in Views** immer mit `esc()` — auch `old()`-Werte in `<textarea>`; destruktive
  Aktionen (Löschen) nur per POST-Formular mit `csrf_field()`.

## Bewusst entfernt — nicht wieder einbauen
Multi-User/Rollen, Session-Timeout-Warnsystem mit Auto-Refresh, Keyboard-Shortcuts,
automatische HV-Begründungs-Generatoren (Freitext reicht), Dashboard-Quick-Upload,
drittes Exportformat (Buchungen-Listen-Excel), DB-Trigger/-Views,
Tabelle `system_einstellungen` (Konfiguration kommt aus `.env`).

## Deployment-Hinweise
- Produktiv: `CI_ENVIRONMENT = production` und starkes `vdst.master_password` in `.env`.
- Nach Code-Deploy: `php spark migrate` (mit DB-Dump + Kopie von `public/uploads/` vorher).
- Docker-Passwörter überschreibbar via Umgebungsvariablen (`DB_PASS`,
  `MYSQL_ROOT_PASSWORD`), Defaults nur für lokale Entwicklung.
