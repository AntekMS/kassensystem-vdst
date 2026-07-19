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
- PhpOffice/PhpSpreadsheet für Excel-Exporte, dompdf für die PDF-Rechnungen
  des Getränkerechnung-Imports (`app/Libraries/RechnungPdf.php`)
- Bootstrap 5 + Bootstrap Icons via CDN, geteiltes JS in `public/js/app.js`;
  Theme/CSS zentral in `public/css/app.css` (siehe Design-System unten), KEINE
  `<style>`-Blöcke mehr in Views (Ausnahmen: seitenspezifische
  `renderSection('styles')` und das Standalone-PDF-Template
  `app/Views/pdf/rechnung.php`, das dompdf ohne app.css rendert)
- Docker-Setup (`docker-compose up -d` → App auf :8080, phpMyAdmin auf :8081)
- Lokal alternativ: `php spark serve` + MySQL (XAMPP-Default in `app/Config/Database.php`)

## Befehle
Auf diesem Rechner gibt es **kein Host-PHP/Composer** — alles im laufenden Web-Container
ausführen (`docker ps` → `kassensystem-vdst-web`, `-db`, `-phpmyadmin`):
- `docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/` — Tests
  (`HealthTest`, `BetragTest`, `SchuldLabelTest`, `GetraenkeBeglichenTest`,
  `GetraenkeImportParserTest`, `RechnungPdfTest`, `RechnungVersandTest`,
  `PersonSchluesselTest`, `PersonModelTest`, `GetraenkeImportAufloeserTest`);
  entspricht `composer test`
- `docker exec kassensystem-vdst-web php spark migrate` — Migrationen (auch die
  Datei-/Trigger-Cleanup-Migrationen)
- `docker exec kassensystem-vdst-web php spark routes` — Routenliste
- `docker exec kassensystem-vdst-web php -l <datei>` — Syntax-Check einzelner Dateien
- Smoke-Test/Login-Flow: `curl` gegen `http://localhost/...` **im Container** (CSRF-Token
  aus der Login-Seite lesen, `vdst.master_password` aus `.env`).
- Backup/Restore: `./scripts/backup.sh` bzw. `./scripts/restore.sh <dump.sql.gz>` laufen
  auf dem **Host** (kein Host-PHP nötig — sie treiben `docker exec` gegen den DB-Container;
  der Web-Container hat keinen MySQL-Client). `--no-tablespaces` im mysqldump ist Absicht
  (kassenuser hat kein PROCESS-Privileg). Details: `docs/BACKUP.md`.

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
  einziger Codepfad für Beleg-Zuordnungen), `PersonModel` (persons —
  Personen-Register mit vorname/nachname/email, autoritative Namens-/E-Mail-Quelle
  seit Issue #61), `GetraenkeVersandModel` (getraenke_versand, Versand-Log).
- **Gemeinsame Views**: `app/Views/abrechnungen/*` werden von AH und HV geteilt,
  gesteuert über `$typ` ('ah'|'hv'). HV hat zusätzlich ein Freitext-Feld `begruendung`.
- **Upload-Logik**: zentral in `app/Libraries/BelegUpload.php` (genutzt von
  BelegeController::store und BuchungenController::store); `speichereBelegAusDatei()`
  legt Belege aus bereits serverseitig liegenden Dateien an (KOPIERT die Quelle —
  so übernimmt der Getränkerechnung-Import seine generierten Temp-PDFs als Belege).
- **Auth**: `App\Libraries\Auth::istAngemeldet()` ist die EINZIGE Login-/Timeout-Prüfung;
  `AuthController::isAuthenticated()`, `AuthFilter` und der 404-Override in `Routes.php`
  delegieren dorthin. Logik nicht erneut duplizieren.
- **Labels**: `app/Helpers/label_helper.php` (autogeladen) — `kategorie_label()`,
  `beleg_status_label()`, `abrechnung_status_label()`, `konto_label()`,
  `schuld_typ_label()`, `schuld_kategorie_label()` (je mit `_optionen()`-Pendant),
  `formatiere_betrag()`, `normalisiere_betrag()`, `schaetze_archiv_groesse()`,
  `person_normalisiere()`/`person_schluessel()` (kanonischer, whitespace- und
  case-normalisierter Vergleichsschlüssel für Freitext-Personennamen — EINZIGE
  Quelle für das Matching zwischen `schulden.person`, dem Personen-Register
  (`persons` via `PersonModel`) und `getraenke_versand`, kein Ad-hoc-
  `mb_strtolower()` daneben; seit Issue #61 zentral über `PersonModel`),
  `person_anker()` (id-/fragment-sicherer Scroll-Anker `person-<slug>` auf Basis
  von `person_schluessel()`, Issue #55).
- **Design-System** (Issue #47): `public/css/app.css` ist die EINZIGE Theme-Quelle,
  eingebunden von `layouts/main.php` und `auth/login.php` (Cache-Buster `?v=N` bei
  CSS-Änderungen hochzählen). Tokens: Vereinsfarben (`--vdst-rot` #dc143c nur als
  Akzent — genau EIN roter `.btn-vdst` = Primäraktion pro Seite), Grau-Rampe
  `--grau-50…900`, Statusfarben, Radius/Schatten. Seit Issue #14 (Vorstufe
  Darkmode) sind ALLE Farbwerte in `app.css` als Custom Properties
  hinterlegt — keine rohen Hex-/rgba()-Literale mehr in den Regeln (nur noch
  in den `:root`-Token-Definitionen selbst); u.a. `--surface` (Kartenflächen,
  vorher verstreutes `#fff`), `--chrome-schwarz` (reines Schwarz von Sidebar/
  Topbar/Login-Header, bewusst getrennt von `--vdst-schwarz`), `--*-rgb`-
  Geschwister-Tokens (`--vdst-weiss-rgb`, `--vdst-schwarz-rgb`, `--vdst-rot-rgb`,
  `--vdst-rot-hover-rgb`) für `rgba(var(--…-rgb), Alpha)`-Nutzung, sowie
  `--vdst-rot-text`/`--badge-gruen-*`/`--badge-amber-*` für die Soft-Badge-
  Textfarben — Voraussetzung für den eigentlichen Darkmode.
  **Darkmode** (Issue #14): Bootstraps natives `data-bs-theme`-Attribut auf
  `<html>` ist der Schalter — ein `[data-bs-theme="dark"]`-Block in `app.css`
  überschreibt die Grau-Rampe (invertiert, nicht 1:1 gespiegelt, auf Kontrast
  auf dunklem Grund hin gewählt), `--surface`, `--bs-body-bg`,
  `--vdst-rot-tint`/`--vdst-rot-text`, `--badge-gruen-*`/`--badge-amber-*`,
  `--status-positiv`/`--status-wartend` sowie `--schatten-sm`/`--schatten`.
  `--vdst-schwarz`, `--vdst-weiss`, `--chrome-schwarz` und die Rot-Töne bleiben
  ABSICHTLICH themeunabhängig (Sidebar/Topbar/Login-Header sind schon dunkel,
  Marke soll sich nicht ändern). Der Segment-Umschalter-Chip
  (`.btn-outline-vdst.active`/`.btn-check:checked`) nutzt deshalb bewusst
  `var(--vdst-schwarz)` statt `var(--grau-900)` als Aktiv-Hintergrund — sonst
  würde er sich im Darkmode zu einer hellen Fläche umkehren. Ein blockierendes
  Inline-`<script>` ganz oben in `<head>` (VOR den Stylesheets, dupliziert in
  `main.php` UND `login.php`, da Login standalone ist) liest `localStorage`
  (`vdst-theme`) bzw. `prefers-color-scheme` und setzt `data-bs-theme`, um
  einen Flash im falschen Theme zu vermeiden. Umschalt-Buttons (Klasse
  `.js-theme-toggle`, Icon `bi-moon-stars`/`bi-sun`) sitzen in
  `.app-sidebar-foot` (Desktop) und `.app-topbar` (Mobile) — Logik in
  `app.js` (`setTheme()`/`syncThemeToggleIcons()`/`bindThemeToggle()`), da
  beide Buttons auf derselben Seite synchron bleiben müssen. Die Login-Seite
  hat einen eigenen Toggle-Button (`#themeToggle` in `.login-header`,
  `.app-theme-toggle`) mit eigenem, dupliziertem Inline-Handler (kein
  `app.js` auf der Login-Seite). Legacy-Klassen (`.btn-vdst`,
  `.btn-outline-vdst`, `.card-vdst`, `.table-vdst`, `.kontostand-card`,
  `.page-title`, `.saldo-positiv/-negativ`) wurden umgestylt, NICHT umbenannt.
  Button-Hierarchie: `.btn-vdst` = rote Primäraktion (max. eine pro Seite),
  `.btn-outline-vdst` = ALLE Sekundäraktionen (kein btn-outline-secondary/-dark/
  -info mehr); Status-Badges NUR über die `*_badge_class()`-Helper in
  `label_helper.php` (Soft-Badges `badge-status-*`), keine `badge bg-*` mehr.
  `style="display:none"` ist nur für JS-gesteuerte Toggles erlaubt — sonstige
  Inline-Styles gehören als Klasse in app.css.
  Layout: schwarze Sidebar (`.app-sidebar`, Bootstrap `offcanvas-lg` — ab lg feste
  Spalte, darunter Drawer per Burger in `.app-topbar`); Aktiv-Zustand über
  `uri_string()`-Checks in `main.php`. `<main class="main-content">` muss diese
  Klasse behalten (app.js `showMessage()` injiziert dorthin). Für Listen-Views
  vorbereitet: `.table-stack` (+ `data-label` je `<td>`, Aktions-Zelle
  `.stack-actions`, Summe als `.summe-mobile d-lg-none`), `.badge-status-*`,
  `.filter-bar`, `.empty-state`, `.btn-icon`. VDSt-Logo (Issue #69) in
  `.app-sidebar-brand` (Desktop-Sidebar + Mobile-Offcanvas, da dieselbe `<aside>`)
  und `.app-topbar-brand` (Mobile-Topbar) über dieselbe Asset-Datei wie das
  PDF-Branding (`public/img/vdst-logo.svg`, schwarzer Trace auf transparentem
  Grund) — auf dem schwarzen Hintergrund per `filter: invert(1)`
  (`.app-sidebar-logo`/`.app-topbar-logo`) in Weiß gedreht statt eine zweite
  Asset-Variante zu pflegen.
- **Geteiltes JS** (`public/js/app.js`, in `layouts/main.php` eingebunden):
  `confirmDelete()`, `showMessage()`, Export-Toasts; Views binden Verhalten per CSS-Klasse
  `js-autosubmit` (Filter-Selects) bzw. `js-betrag-format` (Betrag-Eingaben) — solche
  Handler NICHT wieder inline in Views duplizieren.
- **Exporte**: `app/Helpers/ExcelHelper.php` + `ZipHelper.php`. Pro Bereich genau
  2 Formate: Excel und Komplett-ZIP (Excel + Beleg-Dateien). Ausnahme Schulden:
  die Inventur (`ExcelHelper::erstelleInventur`, ein Sheet "Kassenwart – Aktueller
  Bestand": Kassenbestand + Forderungen − Verbindlichkeiten) hat statt des ZIPs
  einen PDF-Export (Issue #70, `schulden/export/inventur-pdf`,
  `RechnungPdf::inventur()` + `$typ === 'inventur'` im geteilten Template
  `pdf/rechnung.php` — gleiche Datengrundlage wie das Excel, sekundärer
  `.btn-outline-vdst`-Button neben dem primären Excel-Download).
- **Inventur** ist zusätzlich eine eigene HTML-Seite (`GET /inventur` →
  `SchuldenController::inventur`, View `schulden/inventur.php`, eigener Navbar-Punkt
  und Dashboard-Kachel) mit derselben Datengrundlage wie Excel/PDF
  (`BuchungModel::berechneKontostaende()` + `SchuldModel::berechneInventur()`);
  die Downloads bleiben unter `schulden/export/inventur` (Excel) und
  `schulden/export/inventur-pdf` (PDF).
- **Personen-Register** (Issue #61, Fundament für #59): `PersonModel` (Tabelle
  `persons`: vorname/nachname/email/aktiv) ist die autoritative Namens-/E-Mail-Quelle.
  **Registry + Soft-Link** (bewusst KEINE volle Normalisierung): `schulden` hat eine
  NULLABLE `person_id`-FK (`ON DELETE SET NULL`), behält aber `schulden.person` als
  denormalisierten Anzeige-/Gruppierungsschlüssel — Aggregation, Detailseite
  (`?name=`) und Anker/Redirect bleiben namensbasiert. `person_id` ist der Soft-Link,
  auf dem #62 (Nachname→Vollname beim Getränke-Import) aufsetzt; wird an ALLEN
  Insert-Pfaden mit bekanntem Namen gesetzt (`SchuldModel::personId()` in den
  Sync-Methoden, `extrahiereEintrag()` im Controller; beim Import bereits vom
  Resolver aufgelöst, s.u.) — Institutions-Zeilen (AH²-Bund/Heimverein,
  `abrechnung_id` gesetzt) und Gäste bleiben `person_id` NULL. Matching läuft AUSSCHLIESSLICH über `person_schluessel()` — `PersonModel`
  vereinheitlicht damit das frühere Nebeneinander aus DB-Kollation und PHP-Schlüssel
  (pure Seams `anzeigename()`/`baueSchluesselMap()`/`idAusMap()`/`emailsAusMap()`,
  DB-los testbar → `PersonModelTest`). E-Mails liegen in `persons.email`
  (`person_emails` per Migration entfernt). Verwaltung: `/schulden/personen`
  (`personen()`/`personenStore()`/`personenDelete()`), Datalists (schulden/belege/
  buchungen) speisen sich aus `PersonModel::getAnzeigenamen()`.
  E-Mail-Edit an der Person (Issue #58): die Schulden-Detailseite
  (`/schulden/person?name=…`) zeigt eine E-Mail-Karte (Anzeige + Speichern via
  `POST schulden/person/email` → `personEmailStore()` →
  `PersonModel::speichereEmailFuerName()`). SEMANTIK-UNTERSCHIED: dieser
  explizite Edit LÖSCHT bei leerem Feld die gespeicherte Adresse —
  `upsertFuerName()` (Versand-Seite) ignoriert leere Werte weiterhin und bleibt
  unangetastet; Entscheidungslogik im puren Seam `PersonModel::emailAktion()`
  (→ `PersonModelTest`). Unbekannte Namen werden beim Speichern als
  Nachname-Eintrag angelegt; existiert die Person, verlinkt die Karte auf
  `/schulden/personen?edit=<id>`. Institutions-Zeilen (AH²-Bund/Heimverein)
  bekommen KEINE E-Mail-UI — Guard über `SchuldModel::INSTITUTION_PERSONEN` +
  `istInstitution()` (person_schluessel-basiert; die Konstante ist jetzt auch
  die einzige Quelle der Institutions-Namen in `syncAbrechnungForderung`,
  Regression-Pin in `GetraenkeBeglichenTest`).
- **Schulden** (`SchuldenController`/`SchuldModel`, Views `app/Views/schulden/*`):
  Ledger pro Person, Person ist **Freitext-String** mit optionalem Soft-Link ins
  Personen-Register (s.o.; Datalist-Vorschläge aus `persons`, Namen werden getrimmt,
  Gruppierung case-insensitiv über die DB-Kollation).
  Personen-Detail läuft über `/schulden/person?name=…` (GET-Param wegen
  Leerzeichen/Umlauten, kein URI-Segment). Die Übersicht hat Suche (Name),
  Status-Filter und Sortierung als GET-Parameter (`getPersonenUebersicht($filter)`;
  Status filtert aggregierte Summen per HAVING, Sortierung NUR über die
  Whitelist `SchuldModel::SORTIERUNGEN`).
- **Schulden-Verknüpfung** (Issue #38): `schulden` hat Quell-Spalten `beleg_id`/
  `buchung_id` (FK, ON DELETE CASCADE) und `abrechnung_typ`+`abrechnung_id` (kein FK,
  da zwei Abrechnungs-Tabellen) sowie den Personen-Soft-Link `person_id`
  (FK → `persons`, ON DELETE SET NULL, Issue #61). Automatische Einträge entstehen aus drei Quellen:
  (1) Beleg mit `erstattung_person` → Verbindlichkeit
  (`SchuldModel::syncBelegVerbindlichkeit`, Hook in BelegeController::store/update),
  (2) Abrechnungs-Statuswechsel → Forderung gegen „AH²-Bund"/„Heimverein" bei
  eingereicht, zusätzlich negativer Ausgleich bei bezahlt, Zurückstufen entfernt
  die Einträge (`syncAbrechnungForderung`, idempotent; Hook in
  `AbstractAbrechnungenController::changeStatus` — Forderung und Ausgleich werden
  am Vorzeichen unterschieden),
  (3) Buchung mit „Schuld ausgleichen" → negativer Rückzahlungs-Eintrag
  (`erstelleBuchungsAusgleich` bei store, `syncBuchungAusgleich` bei update).
  Automatische Einträge (`SchuldModel::istAutomatisch`) sind in der Schulden-UI
  gesperrt und werden NUR über ihre Quelle gepflegt; Löschen der Quelle räumt
  per FK-Cascade auf (Abrechnungen sind nur als entwurf/ausstehend löschbar,
  wo keine verknüpften Einträge existieren).
- **Getränkerechnung-Import** (Issue #35): `/schulden/import` (Upload → Vorschau →
  Confirm → Versand, SchuldenController::import/importUpload/importConfirm/
  importVersand). Parser `app/Libraries/GetraenkeRechnungImport.php` liest die
  Excel des Getränkewarts:
  Sheet „Bundesbrüder & Gäste" → pro Nachname eine Getränke-Forderung
  über „Gesamt − Ausstehend" (Personenanzahl variabel: ab Zeile 3 bis Trennzeile/
  `Gesamtanzahl:`, `(Einfügespalte)` und 0-Beträge übersprungen; bewusst OHNE
  Quell-Verknüpfung, damit editierbar); Sheet „Coleur & Bund" → zwei
  `ah_berechtigt`-Belege in die offene AH-Abrechnung
  (`AhAbrechnungModel::findeOffeneAbrechnung()`, sonst neue; Monat schon
  eingereicht/bezahlt → unzugeordnet + Warnung). Die Belege sind seit Issue #35
  Teil 3 **generierte PDF-Rechnungen** (`RechnungPdf::coleurBund`, via Temp-Datei
  durch `speichereBelegAusDatei`) — die Original-Excel wird NICHT mehr als Beleg
  aufgehoben (`xlsx` bleibt aber im dateityp-ENUM für Altbelege).
  INVARIANTE: NUR gecachte Formelwerte lesen (`getOldCalculatedValue`), NIE
  `getCalculatedValue()`/`toArray()` — die Sheets „Schwund"/„Bestand" enthalten
  TRANSPOSE-Formeln, an denen PhpSpreadsheet scheitert; sie werden per
  `setLoadSheetsOnly` gar nicht erst geladen. Hochgeladene Datei wird unter
  Zufallsnamen in `writable/uploads/import/` geparkt (Basename in der Session,
  Confirm parst NEU, Altlasten >24h — auch verwaiste Temp-PDFs — werden
  weggeräumt). Doppelimport ist erlaubt, die Vorschau warnt aber (Erkennung über
  die Marker-Spalten `import_monat`/`import_monat_bis`, s.u. Issue #64).
  Nachname→Vollname-Auflösung (Issue #62): der Parser bleibt DB-agnostisch (roher
  Nachname); die Auflösung gegen das Personen-Register liegt im reinen Resolver
  `app/Libraries/GetraenkeImportAufloeser::loese($personen, nachnameMap, $wahlen)`.
  Eindeutiger Nachname-Treffer (`PersonModel::baueNachnameMap`, strikter Match nur
  auf `nachname`) → automatisch Anzeigename + `person_id`; mehrdeutig (≥2 gleiche
  Nachnamen) / unbekannt (0) → in der Vorschau interaktiv per `<select>` zuordnen
  (mehrdeutig: Kandidaten; unbekannt: alle Personen; je + „als Gast übernehmen" =
  roher Nachname, `person_id` NULL). Die Wahl wird index-basiert
  (`nachname[i]`/`wahl[i]`, NIE Freitext-Name als Array-Key) durch den Confirm-POST
  getragen; `importConfirm` liest daher jetzt Session **und** `wahl`-POST
  (`leseImportWahlen()`). Der Resolver validiert die `person_id`-Wahlen serverseitig
  (mehrdeutig: nur unter den Kandidaten; unbekannt: gegen ALLE Personen);
  `erstelleImportForderungen()` speichert die bereits aufgelösten `person`/`person_id`.
  Doppelimport-/grund-/Monatslogik unverändert (nur der gespeicherte Name ändert
  sich). Tests: `GetraenkeImportAufloeserTest`, `baueNachnameMap` in `PersonModelTest`.
  Monat/Zeitraum (Issue #57): Das Monatsfeld in `importUpload()` ist optional —
  bleibt es leer, schlägt `GetraenkeRechnungImport::monatAusDateiname()` einen
  Monat aus dem Original-Dateinamen vor (deutscher Monatsname, Jahr aus dem Namen
  oder plausibel geschätzt: laufendes Jahr, außer der Monat läge >1 Monat in der
  Zukunft → Vorjahr), sonst greift der Vormonat. Optionales „Bis"-Feld (`monat_bis`)
  erlaubt einen Zeitraum; `monatsName($von,$bis)` rendert ihn („November–Dezember
  2025" bzw. „November 2025–Januar 2026"). Der Zeitraum wird als `von`+`bis` in
  Session/Query durchgereicht (`SchuldenController::versandQuery()` baut den
  Query-String, `leseMonatBis()` validiert `bis`).
  Typisierter Import-Marker (Issue #64): `schulden.import_monat` (`JJJJ-MM`,
  Startmonat, indiziert) + `import_monat_bis` (Endmonat bei Zeitraum, sonst NULL)
  identifizieren die Import-Forderungen — der editierbare `grund`
  (`SchuldModel::getraenkeImportGrund()`) ist NUR noch Anzeige-/Editier-Text,
  kein Schlüssel mehr. Normalisierung `bis === von → NULL` zentral über
  `SchuldModel::importMonatBis()` (Parität zu `monatsName()`; auch der
  Reverse-Parser `parseMonatsName()` normalisiert so — es kann nie ein
  unmatchbares von==bis-Paar entstehen); Insert (`erstelleImportForderungen`)
  und `getImportForderungen($monat,$monatBis)` nutzen ausschließlich diese
  Spalten. Der Doppelimport-Check der Vorschau läuft über
  `SchuldModel::zaehleUeberlappendeImportForderungen()` — Overlap-Match statt
  exaktem Zeitraum-Paar (auch ein November-Import nach einem
  November–Dezember-Import fällt auf), gleiche typ/kategorie-Filter wie
  `getImportForderungen`. MANUELL angelegte Getränke-Forderungen mit
  kanonischem Import-grund („Getränkerechnung November 2025") bekommen die
  Marker beim Anlegen automatisch (`SchuldModel::importMarkerAusGrund()` in
  `SchuldenController::store` — verpasste Personen lassen sich so wie vor #64
  zum Abrechnungslauf nachtragen); NUR beim Anlegen — Updates verändern
  bestehende Marker nie. Die Marker sperren die Einträge NICHT
  (`istAutomatisch()` prüft sie bewusst nicht — Import-Forderungen bleiben
  editierbar; Achtung: ein typ/kategorie-Edit nimmt die Zeile trotz Marker
  aus der Versand-Datenquelle — bewusste Filter-Entscheidung). Bestandsdaten
  wurden per Migration `2026-07-18-000001_SchuldenImportMonat` aus dem `grund`
  backfilled; die Migration ist SELBSTSTÄNDIG (eingefrorene Kopie des
  Reverse-Parsers, byte-exakter `strncmp`-Präfix-Guard gegen die
  ci-Kollation, Backfill pro Zeile statt DISTINCT — Migrationen NIE an
  lebenden App-Code koppeln); nicht parsebare Gründe bleiben NULL und tauchen
  — wie vorher — nicht im Versand auf.
  Test: `tests/unit/GetraenkeImportParserTest.php`.
- **PDF-Rechnungen** (Issue #35, seit #70 auch Inventur): `app/Libraries/RechnungPdf.php`
  (dompdf) rendert das geteilte Template `app/Views/pdf/rechnung.php` (gesteuert über
  `$typ`: `einzel`|`uebersicht`|`coleur_bund`|`inventur`; Standalone-HTML, eigener
  `<style>` hier ok). Branding: Logo `public/img/vdst-logo.svg` als Base64-Data-URI +
  Schwarz/Rot-Typografie. INVARIANTEN: `defaultFont 'DejaVu Sans'` + `loadHtml(...,
  'UTF-8')` (sonst kaputte Umlaute/€), `isRemoteEnabled=false`/`isPhpEnabled=false`,
  Font-Cache/TempDir auf `WRITEPATH.'cache/'` (vendor/ evtl. nicht beschreibbar).
  Dateinamen über `RechnungPdf::dateiname()` (ASCII-Slug, Präfix „Getraenkerechnung")
  bzw. `RechnungPdf::inventurDateiname()` (Inventur, Präfix „Inventur" analog zum
  Excel-Dateinamen). Test: `tests/unit/RechnungPdfTest.php`.
- **Rechnungsversand** (Issue #35): `/schulden/import/versand?monat=JJJJ-MM`
  (Redirect-Ziel nach importConfirm, jederzeit erneut aufrufbar) listet die
  importierten Forderungen des Monats mit E-Mail-Feld und Auswahl; „Rechnungen
  verschicken" mailt die personalisierte PDF-Einzelrechnung (`RechnungVersand`,
  CI4-Email-Service, Anhang aus dem Buffer). Datenquelle ist IMMER
  `SchuldModel::getImportForderungen($monat, $monatBis)` (Match über die
  Marker-Spalten `import_monat`/`import_monat_bis`, Issue #64 — NIE über den
  editierbaren `grund` oder gar `LIKE 'Getränkerechnung %'`; Beträge
  nie aus dem POST). Das Versand-Formular ist **index-basiert**
  (`person[i]`/`email[i]`, `senden[]`=Index) — NIE den Freitext-Namen als
  POST-Array-Key benutzen (`]` im Namen zerlegt den Key). Name↔Forderung wird
  über `person_schluessel()` gematcht. Beim Senden werden die Adressen NACH dem
  Upsert frisch aus der DB geladen (`PersonModel::findEmailsFuer`), nicht aus dem
  POST — so greift auch eine schon gespeicherte Adresse, deren Feld leer gepostet
  wurde. Adressen liegen seit Issue #61 in `persons.email` (Upsert beim Versand
  über `PersonModel::upsertFuerName` — legt fehlende Personen als Nachname-Eintrag
  an; Verwaltungsseite `/schulden/personen`); erfolgreiche Sends landen im Log `getraenke_versand`
  („verschickt am"-Badge, Checkbox dann default aus — bewusst keine harte
  Doppelversand-Sperre). Das Log ist wie die Import-Marker auf
  `monat`+`monat_bis` gekeyt (Migration `2026-07-19-000001`, Normalisierung
  über `SchuldModel::importMonatBis`; Alt-Logs haben `monat_bis` NULL =
  Einzelmonat-Semantik) — ein Einzelmonat-Versand markiert auf einer
  Zeitraum-Versandseite niemanden als „verschickt" und umgekehrt. PRG-Redirect
  verhindert Reload-Doppelversand, und die PDF-Erzeugung in der Sende-Schleife
  ist einzeln `try/catch`-gekapselt, damit ein dompdf-Fehler bei Person N
  nicht den ganzen POST abbricht. SMTP kommt aus
  `email.*`-Keys in `.env` (Beispielblock in `env`, inkl. `email.SMTPKeepAlive`
  für den Reihenversand über eine Verbindung); ohne Konfig ist der Versand per
  `RechnungVersand::istKonfiguriert()` deaktiviert, Adressen speichern geht
  trotzdem. Übersichts-PDF (Aushang): `/schulden/import/uebersicht`. Landet der
  Versand nach einem Import auf einem Monat ganz ohne offene Forderungen (alles
  Guthaben), bleibt die Import-Erfolgsmeldung erhalten (nicht als Fehler
  verschlucken → sonst Re-Import).
  Test: `tests/unit/RechnungVersandTest.php`, `tests/unit/PersonSchluesselTest.php`.
- **Mailtext der Einzelrechnung** (Issue #60): `RechnungVersand::baueMail(string
  $person, string $monatsName, string $fristDatum): array` baut Betreff +
  personalisierten Du-Text (Lastschrift-Ankündigung, Rückmelde-Frist,
  optionaler Überweisungs-Absatz, Signatur) — der Betrag steht bewusst NUR im
  PDF-Anhang, nicht mehr im Mailtext. `$fristDatum` kommt als `Y-m-d` rein und
  wird als „{Wochentag}, den {d.m.Y}" ausgegeben (deutsche Wochentagsnamen über
  die private Konstante `RechnungVersand::WOCHENTAGE`, indiziert über
  `date('N')` — kein intl/setlocale-Verlass). Der Überweisungs-Absatz
  („Falls du kein Lastschriftmandat…" bis inkl. Verwendungszweck) wird NUR
  gerendert, wenn `vdst.bank_iban` gesetzt ist; Kontoinhaber/BIC/Bankname
  kommen aus `vdst.bank_kontoinhaber`/`vdst.bank_bic`/`vdst.bank_name`.
  Mail-Signatur/PDF-Fußzeile lesen weiterhin `vdst.kassenwart_name` aus `.env`
  (Fallback „der Kassenwart"), zusätzlich hängt `vdst.kassenwart_zeichen`
  (z.B. Bandzeichen) als „Kassenwart {Zeichen}" an die Signatur an (ohne Key
  einfach „Kassenwart"). Die Rückmelde-Frist wird auf der Versand-Seite
  (`schulden/import_versand.php`) als Datumsfeld `frist` abgefragt, vorbelegt
  mit `SchuldenController::berechneFristDefault()` (Freitag dieser Woche, bei
  bereits vergangenem Freitag der nächste) — `importVersandSenden()` validiert
  den POST-Wert (`istGueltigesDatum()`, Regex + `checkdate()` + Guard gegen
  Vergangenheitsdaten) und fällt bei fehlendem/ungültigem/vergangenem Wert auf
  denselben Default zurück, damit nie eine schon abgelaufene Frist verschickt
  wird. Im Bank-Absatz wird jede Zeile (Kontoinhaber/BIC/Bankname) nur bei
  nicht-leerem Wert gerendert (`array_filter`) — leere `vdst.bank_*`-Werte
  erzeugen so keine kaputten „BIC: "- oder Leerzeilen; IBAN und
  Verwendungszweck stehen immer. Alle `vdst.bank_*`- und
  `vdst.kassenwart_zeichen`-Keys sind auskommentierte Beispiele in `env`.

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
- **Sammel-Zuordnung** (Issue #11): „Alle hinzufügen"-Button (select_belege, AJAX
  `addAlleBelege`) und Checkbox „alle Belege direkt übernehmen" (create/store) laufen
  beide über `AbrechnungBelegModel::fuegeAlleVerfuegbarenHinzu()` — jeder Beleg einzeln
  durch `fuegeZuordnungHinzu()` (alle Guards bleiben aktiv), `berechneGesamtsumme()`
  genau EINMAL nach dem Batch. Keinen Bulk-Insert daran vorbei bauen.
- **Schulden-Vorzeichen**: Beträge normal positiv, **Rückzahlungen negativ** (grün
  dargestellt); offener Stand = einfache `SUM(betrag)`. `typ` trennt Forderung
  (Person schuldet Verein) und Verbindlichkeit (Verein schuldet Person) — die beiden
  werden NIE gegeneinander verrechnet. Betrag 0 lehnt der Controller ab. Ab
  `GETRAENKESTOPP_LIMIT` (50 €, `Config/Constants.php`) Getränke-Forderungen zeigt
  die UI ein Getränkestopp-Badge.
- **1-Klick-Getränkeausgleich** (Issue #43): „Beglichen"-Button (Schulden-Index +
  Personen-Seite) legt einen manuellen negativen Getränke-Eintrag über die volle
  offene Forderung an; erkannt über `SchuldModel::GETRAENKE_BEGLICHEN_GRUND` +
  `istGetraenkeAusgleich()` (KEIN Ad-hoc-Grund-Matching). „Rückgängig" wird nur
  angeboten, solange der Ausgleich der neueste Getränke-Eintrag der Person ist —
  danach normal in der Personen-Ansicht löschen. Beide Buttons bewusst ohne
  JS-Confirm (gegenseitig 1-Klick-umkehrbar); Test: `tests/unit/GetraenkeBeglichenTest.php`.
- **Massen-Getränkeausgleich** (Issue #55): „Alle Getränke begleichen" in der
  Übersichts-Aktionsleiste (nur sichtbar, wenn es offene Getränke-Forderungen
  gibt; `.btn-outline-vdst`, da „Neuer Eintrag" die eine rote Primäraktion ist)
  ruft `SchuldenController::getraenkeAlleBeglichen` → `SchuldModel::begleicheAlleGetraenke()`,
  das über `getOffeneGetraenkeForderungen()` (aggregierte offene Getränke-Summen
  je Person) iteriert und pro Person denselben `erstelleGetraenkeAusgleich`
  aufruft wie der Einzel-Button. Der Batch läuft in einer Transaktion
  (`transStart`/`transComplete`, ganz-oder-gar-nicht); der reine Loop steckt im
  DB-los testbaren Seam `erstelleAlleGetraenkeAusgleiche()`. Auch ohne
  JS-Confirm; Redirect an den Listenanfang (`/schulden`, kein Personen-Anker).
  Route `POST schulden/getraenke-alle-beglichen`.
- **Kein Scroll-Sprung** (Issue #55): die Einzel-Pfade `getraenkeBeglichen`/
  `getraenkeBeglichenUndo` redirecten über `beglichenRedirect()` zurück zur
  Herkunftsseite MIT Fragment `#` . `person_anker($name)`; die Übersicht setzt
  dieselbe id auf die `<tr>`, sodass der Browser an der Zeile stehen bleibt.
  `person_anker()` (label_helper) baut auf `person_schluessel()` auf und macht
  den Schlüssel id-/fragment-sicher (Nicht-`[a-z0-9]` → `-`) — Fragment und
  `<tr id>` bleiben so garantiert identisch. SICHERHEIT: das Redirect-Ziel kommt
  aus `previous_url()` (fällt auf den angreiferbeeinflussbaren HTTP_REFERER
  zurück) → `SchuldenController::sameSiteRuecksprungPfad()` übernimmt NUR
  Path+Query und auch das nur bei Host == base_url-Host, sonst Default
  `/schulden` (Open-Redirect-Schutz; pure static, parse_url-basiert). Im
  Non-Rewrite-Betrieb (kein `.htaccess`/mod_rewrite, URLs enthalten
  `/index.php/…`) schneidet `entferneIndexPagePraefix()` ein führendes
  `/{indexPage}`-Segment aus dem übernommenen Pfad heraus (Issue #72) — sonst
  hängt `redirect()->to()` es über `site_url()` ein zweites Mal an
  (`index.php/index.php/schulden` → 404). `$indexPage` kommt in
  `beglichenRedirect()` aus `config('App')->indexPage`. Tests:
  `person_anker` in `PersonSchluesselTest.php`, Massen-Ausgleich (DB-los über den
  `getOffeneGetraenkeForderungen()`-Seam) in `GetraenkeBeglichenTest.php`,
  Redirect-Guard (inkl. Index-Page-Präfix) in `RedirectSameSiteTest.php`.
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
- **UI-Icons**: Bootstrap Icons (`<i class="bi bi-…" aria-hidden="true"></i>`, CDN-Link in
  `layouts/main.php` UND `auth/login.php` — Login ist standalone), KEINE Emojis. Icons sind
  dekorativ neben Textlabels; Icon-only-Buttons brauchen `title` + `aria-label`. In
  `<option>`-Elementen keine Icons (HTML wird dort nicht gerendert). Ladezustände mit
  Bootstrap-Spinner (`spinner-border spinner-border-sm`), nicht mit Icon.

## Bewusst entfernt — nicht wieder einbauen
Multi-User/**Login-Rollen**, Session-Timeout-Warnsystem mit Auto-Refresh, Keyboard-Shortcuts,
automatische HV-Begründungs-Generatoren (Freitext reicht), Dashboard-Quick-Upload,
drittes Exportformat (Buchungen-Listen-Excel), DB-Trigger/-Views,
Tabelle `system_einstellungen` (Konfiguration kommt aus `.env`).

## Bewusst eingeführt (Philosophie-Wende)
- **Personen-Register `persons`** (Issue #61, Fundament für #59): Nachdem „keine
  Personen-Tabelle" lange bewusste Doktrin war, wurde eine echte Personen-Tabelle
  (vorname/nachname/email) eingeführt — Voraussetzung für #62 (Nachname→Vollname)
  und #63, und erster konkreter Schritt Richtung Vision #1. **Wichtig:** Das ist
  KEIN Einstieg in Multi-User/Login-Rollen (die bleiben draußen) — es geht um ein
  Stammdaten-Register der Aktiven, nicht um Auth/Berechtigungen. Umgesetzt als
  Registry + Soft-Link, damit der bestehende schlanke Schulden-Code (namensbasierte
  Aggregation) unverändert bleibt (s. Architektur-Abschnitt „Personen-Register").

## Deployment-Hinweise
- Produktiv: `CI_ENVIRONMENT = production` und starkes `vdst.master_password` in `.env`.
- Nach Code-Deploy: `php spark migrate` (vorher `./scripts/backup.sh` — sichert DB-Dump
  und `public/uploads/`; Cron-Setup und Recovery-Runbook in `docs/BACKUP.md`).
- Docker-Passwörter überschreibbar via Umgebungsvariablen (`DB_PASS`,
  `MYSQL_ROOT_PASSWORD`), Defaults nur für lokale Entwicklung.
