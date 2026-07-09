# VDSt Kassensystem

## Zweck & Maßstab
Kassenbuch- und Abrechnungssystem für den Verein deutscher Studenten zu Erlangen.
**Ein** Kassenwart (Ehrenamt), ~12 Aktive, ein paar Belege pro Woche. Das Tool ist
bewusst klein: keine Multi-User-Verwaltung, keine Rollen, kein Enterprise-Feature-Creep.
Aufgaben: Belege sammeln, Kassenbuch mit 3 Konten führen, monatliche Abrechnungen
an AH²-Bund und Heimverein (HV) als Excel/ZIP einreichen.

## Stack
- CodeIgniter 4 (PHP 8.1+), MySQL 8
- PhpOffice/PhpSpreadsheet für Excel-Exporte
- Bootstrap 5 via CDN, geteiltes JS in `public/js/app.js`, Rest inline in den Views
- Docker-Setup (`docker-compose up -d` → App auf :8080, phpMyAdmin auf :8081)
- Lokal alternativ: `php spark serve` + MySQL (XAMPP-Default in `app/Config/Database.php`)

## Befehle
- `php spark migrate` — Migrationen (auch die Datei-/Trigger-Cleanup-Migrationen)
- `composer test` — PHPUnit (`tests/unit/HealthTest.php`)
- `php spark routes` — Routenliste

## Architektur-Landkarte
- **Controller** (`app/Controllers/`): `DashboardController`, `BelegeController`,
  `BuchungenController`, `AuthController` sowie `AbstractAbrechnungenController`
  mit den dünnen Subklassen `AhAbrechnungenController`/`HvAbrechnungenController`
  (nur `$typ`, `$typName`, Modell — die ganze Logik liegt in der Basisklasse).
- **Models**: `BelegModel` (belege), `BuchungModel` (buchungen),
  `AhAbrechnungModel`/`HvAbrechnungModel` (ah_/hv_abrechnungen),
  `AbrechnungBelegModel` (Junction abrechnung_belege — einziger Codepfad für
  Beleg-Zuordnungen).
- **Gemeinsame Views**: `app/Views/abrechnungen/*` werden von AH und HV geteilt,
  gesteuert über `$typ` ('ah'|'hv'). HV hat zusätzlich ein Freitext-Feld `begruendung`.
- **Upload-Logik**: zentral in `app/Libraries/BelegUpload.php` (genutzt von
  BelegeController::store und BuchungenController::store).
- **Labels**: `app/Helpers/label_helper.php` (autogeladen) — `kategorie_label()`,
  `beleg_status_label()`, `abrechnung_status_label()`, `konto_label()`,
  `formatiere_betrag()`, `normalisiere_betrag()`.
- **Exporte**: `app/Helpers/ExcelHelper.php` + `ZipHelper.php`. Pro Bereich genau
  2 Formate: Excel und Komplett-ZIP (Excel + Beleg-Dateien).

## Konventionen & Invarianten
- **Upload-Pfade** sind relativ zu FCPATH (= `public/`): `uploads/belege/YYYY/MM/`.
  NIE `public/` voranstellen — das war ein historischer Bug (Dateien in
  `public/public/…`), den `2026-07-08-000001_FixUploadPfade` migriert hat.
- **Belegnummern**: `YYYY-MM-DD-NNN` aus dem Rechnungsdatum; Dateiname = Belegnummer.
- **Beträge**: DECIMAL(10,2) in der DB; Eingaben werden vor der Validierung mit
  `normalisiere_betrag()` normalisiert (deutsches Komma erlaubt).
- **Gesamtsummen** der Abrechnungen berechnet PHP (`berechneGesamtsumme()`) bei jedem
  Hinzufügen/Entfernen — es gibt KEINE DB-Trigger mehr (per Migration entfernt).
- **Auth**: ein Master-Passwort aus `.env` (`vdst.master_password`), Session 8h
  (`Config/Session.php::$expiration` muss zu `vdst.session_timeout` passen).
  Auth-Filter kommt ausschließlich aus der Routen-Gruppe in `Routes.php`.
- **CSRF** ist global aktiv (Token rotiert pro POST). AJAX-Antworten der
  Abrechnungs-Endpoints liefern immer `csrf_hash` zurück; das JS in
  `select_belege.php` aktualisiert den Token daraus.
- **Ausgaben in Views** immer mit `esc()`; destruktive Aktionen (Löschen) nur per
  POST-Formular mit `csrf_field()`.

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
