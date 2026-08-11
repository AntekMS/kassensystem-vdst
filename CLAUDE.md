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
- Docker-Setup (`docker-compose up -d` → App auf :8080, phpMyAdmin auf :8081);
  Dev-Mails fängt ein **Mailpit**-Container ab (UI http://localhost:8025 —
  läuft separat im Compose-Netz, Startbefehl in der README, `email.*` in
  `.env` zeigt auf `mailpit:1025`)
- Lokal alternativ: `php spark serve` + MySQL (XAMPP-Default in `app/Config/Database.php`)

## Befehle
Auf diesem Rechner gibt es **kein Host-PHP/Composer** — alles im laufenden Web-Container
ausführen (`docker ps` → `kassensystem-vdst-web`, `-db`, `-phpmyadmin`):
- `docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/` — Tests
  (`HealthTest`, `BetragTest`, `SchuldLabelTest`, `GetraenkeBeglichenTest`,
  `GetraenkeImportParserTest`, `RechnungPdfTest`, `RechnungVersandTest`,
  `PersonSchluesselTest`, `PersonModelTest`, `GetraenkeImportAufloeserTest`,
  `SchuldPositionTest`, `AbrechnungVersandTest`, `ZipDateinameTest`);
  entspricht `composer test`
- `docker exec kassensystem-vdst-web php spark migrate` — Migrationen (auch die
  Datei-/Trigger-Cleanup-Migrationen)
- `docker exec kassensystem-vdst-web php spark abrechnungen:versenden` — automatischer
  Monats-Versand der offenen Abrechnungen (Issue #37, s.u.); für Host-Cron via
  `scripts/abrechnungen-versenden.sh`
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
  `SchuldModel` (schulden), `AbstractAbrechnungModel` mit den dünnen Subklassen
  `AhAbrechnungModel`/`HvAbrechnungModel` (ah_/hv_abrechnungen; Issue #89 —
  spiegelbildlich zur Controller-Abstraktion: die Subklassen setzen NUR `$table`,
  `$typ` ('ah'|'hv'), `$typLabel` ('AH²'/'HV' für Default-Titel und Fehlertexte,
  bewusst NICHT identisch mit `AbstractAbrechnungenController::$typName`, das
  'Heimverein' ausschreibt) und `$berechtigtKategorie`; HV ergänzt im Konstruktor
  nur `begruendung` in `$allowedFields`/`$validationRules` — die ganze Logik inkl.
  der Beleg-Status-Invarianten liegt EINMAL in der Basisklasse. `getMonatName()`
  delegiert an `GetraenkeRechnungImport::monatsName()`, die eine Monatsnamen-Quelle.
  Eigene Fachfehler wie der Doppelmonat-Guard laufen über `$eigeneFehler` +
  überschriebenes `errors()` — `BaseModel::errors()` liest nur Validation und DB,
  ein rohes `$this->errors = […]` käme beim Controller NIE an),
  `AbrechnungBelegModel` (Junction abrechnung_belege —
  einziger Codepfad für Beleg-Zuordnungen; `setzeStatusWennUnbenutzt()` ist der
  gemeinsame Pfad „Beleg in keiner Abrechnung mehr → zurück auf `erfasst`" für
  `entferneZuordnung` und `loescheAlleZuordnungen`), `PersonModel` (persons —
  Personen-Register mit vorname/nachname/email, autoritative Namens-/E-Mail-Quelle
  seit Issue #61), `GetraenkeVersandModel` (getraenke_versand, Versand-Log),
  `SchuldPositionModel` (schuld_positionen — Getränkedetails je Import-Forderung,
  Issue #63, siehe „Getränkedetails" unten).
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
  von `person_schluessel()`, Issue #55), `bank_daten()` (Issue #96 — liest die
  Vereins-Bankverbindung `vdst.bank_*` aus der `.env`; EINZIGE Quelle dieser Keys,
  genutzt vom Mailtext `RechnungVersand::baueMail` UND dem allgemeinen Rechnungs-PDF;
  `konfiguriert` hängt allein an der IBAN).
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
  Bootstraps `.table-light`-Utility verdrahtet `color`/`--bs-table-*` fest
  (`#000`/`#f8f9fa`) und invertiert nicht — daher in `app.css` ein generischer
  token-basierter `.table-light`-Override (beide Table-Variablen UND `color` aus
  der Grau-Rampe), damit Inventur-Kopfzeilen (I/II/III) und Abrechnungs-Gesamtsumme
  im Darkmode lesbar bleiben (Issue #79).
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
- **Geteiltes JS** (`public/js/app.js`, in `layouts/main.php` eingebunden mit
  eigenem Cache-Buster `?v=N` — analog zu `app.css` bei JS-Änderungen
  hochzählen, sonst bleibt bei Bestandsnutzern die alte Version im
  Browser-Cache hängen, da die Datei ohne Query-String nur `ETag`/
  `Last-Modified` mitbekommt, kein `Cache-Control`):
  `confirmDelete()`, `showMessage()`, Export-Toasts; Views binden Verhalten per CSS-Klasse
  `js-autosubmit` (Filter-Selects) bzw. `js-betrag-format` (Betrag-Eingaben) — solche
  Handler NICHT wieder inline in Views duplizieren.
- **Exporte**: `app/Helpers/ExcelHelper.php` + `ZipHelper.php`. Pro Bereich genau
  2 Formate: Excel und Komplett-ZIP (Excel + Beleg-Dateien). Das Abrechnungs-Excel
  baut EINE Methode `ExcelHelper::erstelleAbrechnung($abrechnung, $belege, $typ)`
  (Issue #90 — vorher `erstelleAhAbrechnung`/`erstelleHvAbrechnung` zu ~85 %
  doppelt); `$typ` steuert nur Sheet-/Default-Titel, Header-Füllfarbe
  (`DDDDDD` AH / `FFE4B5` HV) und den HV-`begruendung`-Block, der die Tabelle
  um eine Zeile nach unten schiebt (`$headerZeile` 2 → 3; alle Format- und
  Rahmenbereiche hängen daran). Damit sind auch die `ah`/`hv`-Dispatch-Zweige in
  `AbstractAbrechnungenController::exportExcel` und `ZipHelper::erstelleBelegeZip`
  weg — keine neuen daneben bauen. **`ZipHelper::erstelleArchiv()`** ist seit
  Issue #91 der EINZIGE Codepfad, der ein Export-ZIP zusammenbaut (Abrechnung,
  Belege-Liste, Kassenbuch): Aufrufer liefern Spreadsheet, Dateiliste
  (`['pfad','name','label']`) und eine Abschluss-Callback, die aus der Liste der
  fehlenden Dateien `[Name, Inhalt]` oder `null` macht (Abrechnung: immer
  `00_Info.txt`; die beiden Listen-Exporte: `00_Hinweise.txt` nur bei Lücken).
  Excel geht über eine `uniqid()`-Temp-Datei und wird danach gelöscht. Die
  Sanitize-Regel für ZIP-Dateinamen lebt einmal in
  `ZipHelper::dateinameTeil($text, $maxLen)`; `ZipHelper::belegDateiname()`
  baut daraus `NN_Belegnummer_Beschreibung.endung` (Limit 40 für Abrechnungen,
  30 für die Belege-Liste). Der Kassenbuch-Beleg-Name hat bewusst eine EIGENE
  Form (Buchungsdatum als zweites Segment, Endung aus dem Dateipfad) und teilt
  nur den Sanitizer. Download-Dateinamen der Exporte baut
  `BaseController::exportDateiname($prefix,$filter,$ext,$filterSchluessel,$leerText)`
  — Datumsblock gemeinsam, die zusätzlichen Filter gibt der Aufrufer vor
  (Belege: `kategorie`,`status` + Platzhalter `alle`; Buchungen: `konto_typ`,
  ohne Platzhalter — dieser Unterschied ist gewollt). Abrechnungen haben
  seit Issue #83 zusätzlich einen **PDF-Rechnungs-Export** (`abrechnungen/{typ}/exportPdf/{id}`
  → `AbstractAbrechnungenController::exportPdf`, `RechnungPdf::abrechnung`, s.u.
  „PDF-Rechnungen") — bewusst ERGÄNZEND (Excel/ZIP bleiben), als weiterer Eintrag
  im Export-Dropdown (index) bzw. dritte Export-Karte (preview). Ausnahme Schulden:
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
- **Muster/Vorlagen** (`/muster`, `MusterController`, View `muster/index.php`):
  eine Seite, die JEDES vom System erzeugte Dokument mit **fiktiven Platzhalter-
  daten** (fest im Controller, KEINE DB-Zugriffe) vorführt — 7 PDFs inline im
  neuen Tab (`MusterController::zeige` → `setHeader('Content-Disposition','inline')`,
  Muster aus `SchuldenController::importEinzelPdf`), 4 Excel als Download
  (`ExcelHelper::downloadExcel`, kein Inline). Ruft dieselben reinen Generatoren
  wie der Echtbetrieb (`RechnungPdf::{einzel,allgemein,uebersicht,coleurBund,abrechnung,
  inventur}`, `ExcelHelper::erstelle{Kassenbuch,Abrechnung,Inventur}` — die beiden
  Abrechnungs-Kacheln reichen dabei `'ah'`/`'hv'` explizit durch)
  — keine zweite Rendering-Logik. Registry `MusterController::muster()` ist die
  EINZIGE Quelle für Index-Kacheln UND Dispatch (Slug-Whitelist → sonst 404).
  INVARIANTE: die Getränkepositionen der `einzel`/`coleurBund`-Muster summieren
  sich exakt auf den Betrag, sonst verwirft sie `RechnungPdf::positionenFuer`
  (Summen-Guard). Das Komplett-ZIP hat bewusst KEIN Muster (bündelt echte
  Beleg-Dateien) — nur als Hinweistext auf der Seite erklärt.
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
- **Getränkedetails** (Issue #63, #59b): der Parser liest zusätzlich zu den
  Summen die Einzelpositionen (Menge je Getränk × Einzelpreis) — die
  Getränkespalten stehen zwischen Namens- und „Getränke"-Summenspalte (Namen
  Zeile 1, Preise Zeile 2), im Coleur/Bund-Sheet je Block zwischen Label- und
  „Gesamt:"-Spalte. Die Internet-Pauschale hat KEINEN Stückpreis in Zeile 2
  (dort steht der Umlage-Topf) — sie wird als Rest `Betrag − Getränkesumme`
  übernommen, nur wenn die Internet-Spalte gezählt ist. Positionen sind
  BEST-EFFORT: trifft die Positionssumme die gecachten Summenwerte nicht
  (editierte Datei, unerklärbarer Rest), bleibt die Liste leer und nur der
  Endbetrag zählt — Beträge werden NIE aus Positionen berechnet. Speicherung
  in `schuld_positionen` (`SchuldPositionModel`, FK `schuld_id` ON DELETE
  CASCADE, Migration `2026-07-19-000002`) beim Anlegen der Import-Forderungen;
  Coleur/Bund-Positionen werden NICHT gespeichert (deren PDF-Beleg entsteht
  sofort beim Import aus dem Parse-Ergebnis). Der Aufloeser (#62) reicht
  `positionen` unverändert durch. Rendering-Invariante: `RechnungPdf::
  positionenFuer()` (→ purer Seam `SchuldPositionModel::summePasst()`) lässt
  Details nur ins PDF, wenn ihre Summe den autoritativen `schulden.betrag`
  trifft — eine nachträglich editierte Forderung fällt automatisch auf die
  reine Gesamtsumme zurück (Rechnungslayout wie vor #63). Beim Versand werden
  die Positionen über die `ids`-Spalte (GROUP_CONCAT in
  `getImportForderungen`) geladen und via purem Seam
  `SchuldPositionModel::fuegeZusammen()` gemerged (Doppelimport: gleiche
  Bezeichnung+Einzelpreis addiert). Die Import-Vorschau zeigt die Positionen
  als muted-Zeile unter dem Nachnamen. Manuell nachgetragene Forderungen
  haben keine Positionen → PDF zeigt wie bisher nur den Betrag.
  Tests: `SchuldPositionTest`, Positions-Fälle in
  `GetraenkeImportParserTest`/`RechnungPdfTest`/`GetraenkeImportAufloeserTest`.
- **PDF-Rechnungen** (Issue #35, seit #70 auch Inventur, seit #83 auch Abrechnung,
  seit #96 auch allgemeine Rechnung):
  `app/Libraries/RechnungPdf.php` (dompdf) rendert das geteilte Template
  `app/Views/pdf/rechnung.php` (gesteuert über `$typ`:
  `einzel`|`allgemein`|`uebersicht`|`coleur_bund`|`inventur`|`abrechnung`; Standalone-HTML, eigener
  `<style>` hier ok). `einzel` und `coleur_bund` nehmen optional Getränkedetails
  (`$positionen`, Issue #63 — s.o., inkl. Summen-Guard `positionenFuer()`).
  `abrechnung` (Issue #83, `RechnungPdf::abrechnung($typName,$monatsName,$abrechnung,$belege,$datum)`)
  ist das VDSt-gebrandete PDF-Pendant zum Abrechnungs-Excel — Belegliste
  (Beschreibung/Datum/Beleg-Nr./Betrag/Bezugsquelle) + Gesamtsumme, bei HV
  zusätzlich die Freitext-`begruendung`; **ERGÄNZT** den Excel-/ZIP-Export
  bewusst, ersetzt ihn NICHT.
  `allgemein` (Issue #96, `RechnungPdf::allgemein($person,$positionen,$betrag,$datum,$verwendungszweck,$bank)`)
  ist die allgemeine Rechnung über beliebige offene Forderungen einer Person
  (Spenden/Schulden, s.u. „Allgemeine Rechnung") — Positionsliste
  (Beschreibung/Datum/Betrag) + Gesamtsumme (im Template aus den Positionen
  abgeleitet, KEIN Summen-Guard) + **Überweisungs-Block** (IBAN/Kontoinhaber/BIC/
  Bankname/Verwendungszweck, jede Zeile nur bei nicht-leerem Wert, gleiches Muster
  wie `RechnungVersand::baueMail`). Bewusst PURE: `$bank` kommt als Parameter
  (aus `bank_daten()`), kein env-Zugriff in der PDF-Schicht → im Muster mit
  Beispiel-Bankdaten unabhängig von der `.env` darstellbar.
  Branding: Logo `public/img/vdst-logo.svg` als Base64-Data-URI +
  Schwarz/Rot-Typografie. INVARIANTEN: `defaultFont 'DejaVu Sans'` + `loadHtml(...,
  'UTF-8')` (sonst kaputte Umlaute/€), `isRemoteEnabled=false`/`isPhpEnabled=false`,
  Font-Cache/TempDir auf `WRITEPATH.'cache/'` (vendor/ evtl. nicht beschreibbar).
  Dateinamen über `RechnungPdf::dateiname()` (ASCII-Slug, Präfix „Getraenkerechnung"),
  `RechnungPdf::rechnungDateiname()` (allgemeine Rechnung, Präfix „Rechnung") bzw.
  `RechnungPdf::inventurDateiname()` (Inventur, Präfix „Inventur" analog zum
  Excel-Dateinamen) — alle drei über den gemeinsamen privaten Slugifier
  `slugDateiname()`. Test: `tests/unit/RechnungPdfTest.php`.
- **Allgemeine Rechnung** (Issue #96): Rechnung über beliebige offene Forderungen
  einer Person (Spenden/Einzelforderungen bis „alle offenen Schulden") inkl.
  Überweisungsdetails. Zwei Inline-PDF-Vorschau-Endpoints (kein Log; Muster wie
  `importEinzelPdf`): `GET schulden/person/rechnung?name=…` (`personRechnungPdf()`,
  ALLE offenen Forderungen der Person) und `GET schulden/rechnung?id=…`
  (`einzelRechnungPdf()`, eine einzelne Forderung). Datenquelle:
  `SchuldModel::getForderungenFuerPerson()` — bewusst NUR `typ='forderung'` (keine
  Verrechnung mit Verbindlichkeiten, Vorzeichen-Invariante) und ALLE Forderungen
  (Getränke-Import + manuell) inkl. negativer Ausgleiche, sodass die gelisteten
  Positionen sich exakt auf den offenen Netto-Restbetrag summieren. `< 0.01` →
  Redirect mit Hinweis (nichts zu berechnen). Geteilte private Seams
  (`forderungenAlsPositionen()`, `baueRechnungPdf()`, `ladeForderung()`) halten
  Vorschau und Versand byte-identisch.
  **E-Mail-Versand** (`POST schulden/person/rechnung/senden?name=…` →
  `personRechnungSenden()` → privater `sendeRechnung()`): mailt die Gesamtrechnung
  an die im Register hinterlegte Adresse (`PersonModel::findEmailsFuer`, NICHT aus
  dem POST — der Name ist nur der Schlüssel), Gate über
  `RechnungVersand::istKonfiguriert()`, PDF-Erzeugung + Versand try/catch-gekapselt,
  Redirect mit Flash. Kein Versand-Log (bewusst keine Doppelversand-Sperre,
  Skala von einer Handvoll Belegen/Woche). Mailtext: reine
  `RechnungVersand::baueAllgemeineRechnungMail($person)` (Betrag + Bankdaten stehen
  bewusst NUR im PDF-Anhang, kein Monats-/Fristbezug); die Grußformel aller drei
  Mail-Builder (`baueMail`/`baueAbrechnungMail`/`baueAllgemeineRechnungMail`) liegt
  jetzt im gemeinsamen privaten Seam `RechnungVersand::signatur()`.
  UI-Einstiege in `schulden/person.php`: „Gesamtrechnung ansehen" (nur bei offenen
  Forderungen) und „Rechnung versenden" (POST + JS-Confirm, nur bei
  `!ist_institution && smtp_ok && register_person.email`) in der Aktionsleiste,
  plus je positiver Forderungszeile ein „Rechnung"-Link (read-only, daher auch auf
  automatischen Einträgen). Bankdaten aus `bank_daten()`. Muster: Slug
  `allgemeine-rechnung`. Tests: `RechnungPdfTest`, `RechnungVersandTest`.
- **Rechnungsversand** (Issue #35): `/schulden/import/versand?monat=JJJJ-MM`
  (Redirect-Ziel nach importConfirm, jederzeit erneut aufrufbar) listet die
  importierten Forderungen des Monats mit E-Mail-Feld und Auswahl; „Rechnungen
  verschicken" mailt die personalisierte PDF-Einzelrechnung (`RechnungVersand`,
  CI4-Email-Service, Anhang aus dem Buffer). Je Zeile gibt es eine
  **Einzelrechnungs-Vorschau** (`GET schulden/import/rechnung?monat=…&person=…`
  → `importEinzelPdf()`, PDF inline im neuen Tab): exakt dasselbe PDF wie der
  Mail-Anhang (gleiche Datenquelle, Positionen über den geteilten Helper
  `ladeImportPositionen()`, gleicher Summen-Guard), ohne Versand und ohne
  Log-Eintrag; der `person`-GET-Parameter ist nur Suchschlüssel
  (person_schluessel-Match), Name/Betrag kommen aus der DB. Datenquelle ist IMMER
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
  über `PersonModel::upsertFuerNameMitMap` — legt fehlende Personen als
  Nachname-Eintrag an; Verwaltungsseite `/schulden/personen`). Die Schleife über
  die Empfänger lädt die Schlüssel-Map seit Issue #92 EINMAL davor und reicht sie
  **per Referenz** durch; die Variante pflegt sie nach Insert/Adressänderung mit,
  sonst legte ein zweiter Treffer desselben Namens die Person doppelt an. Das
  bequeme `upsertFuerName()` (lädt selbst) bleibt für Einzelaufrufe — NICHT
  wieder in eine Schleife stecken. Erfolgreiche Sends landen im Log `getraenke_versand`
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
- **Abrechnungs-Auto-Versand** (Issue #37): CLI-Command
  `app/Commands/AbrechnungenVersenden.php` (`php spark abrechnungen:versenden`,
  Gruppe `App`) schickt die je **offene** (`entwurf`/`ausstehend`) AH- **und**
  HV-Abrechnung als **Komplett-ZIP (Excel + Belege)** per E-Mail an den
  **Kassenwart selbst** (`vdst.kassenwart_email`) — zum Prüfen/Weiterleiten,
  **Status bleibt UNVERÄNDERT** (kein Auto-`eingereicht`). Iteriert über AH/HV,
  je `findeOffeneAbrechnung()` (seit #89 einmal in `AbstractAbrechnungModel`, also
  für AH und HV per Konstruktion identisch) +
  `getBelege()`; reiner Seam `AbrechnungenVersenden::sollVersenden(?$abr,$belege)`
  überspringt fehlende/beleglose Abrechnungen; pro Typ `try/catch`. Anhang-Aufbau
  gekapselt in `baueAnhang()` (`ZipHelper::erstelleBelegeZip` → Buffer → `unlink`,
  MIME `application/zip`) — **bewusst isoliert**, weil Issue #83 den Anhang künftig
  auf eine echte PDF-Rechnung umstellen will (dann nur hier ändern). Mailtext:
  `RechnungVersand::baueAbrechnungMail($typName,$monatsName)` (rein, sachlich,
  Signatur wie `baueMail`). Gate: `RechnungVersand::istKonfiguriert()` **und**
  `vdst.kassenwart_email` gesetzt — sonst sauberer Abbruch. `RechnungVersand::sende()`
  nimmt jetzt optional `$mime='application/pdf'` (rückwärtskompatibel; ZIP reicht
  `application/zip` durch). Deutscher Monatsname via `public`
  `AbstractAbrechnungModel::getMonatName()`, das seit #89 an
  `GetraenkeRechnungImport::monatsName()` delegiert (EINE Monatsnamen-Quelle —
  keine neue 12-Monats-Map irgendwo aufspannen). Host-Cron via
  `scripts/abrechnungen-versenden.sh` (Muster `backup.sh`, z.B. `0 8 1 * *`).
  `vdst.kassenwart_email` ist auskommentiertes Beispiel in `env`. Test:
  `tests/unit/AbrechnungVersandTest.php` (DB-los: `baueAbrechnungMail` + `sollVersenden`).

## Konventionen & Invarianten
- **Upload-Pfade** sind relativ zu FCPATH (= `public/`): `uploads/belege/YYYY/MM/`.
  NIE `public/` voranstellen — das war ein historischer Bug (Dateien in
  `public/public/…`), den `2026-07-08-000001_FixUploadPfade` migriert hat.
- **Beleg-Dateien NIE direkt ausliefern**: `public/uploads/.htaccess` setzt
  `Require all denied`. DocumentRoot ist `public/`, und die `public/.htaccess`
  leitet nur NICHT existierende Dateien an `index.php` (`RewriteCond !-f`) —
  echte Upload-Dateien würde Apache sonst am AuthFilter vorbei direkt
  ausliefern (Belegnummern `JJJJ-MM-TT-NNN` sind ratbar → Finanzbelege ohne
  Login abrufbar). Die App liest Beleg-Dateien ausschließlich serverseitig
  (FCPATH-basiert) über die authentifizierten Routen `belege/download/{id}`
  und `belege/preview/{id}` — das Deny bricht also nichts. Achtung: `.htaccess`
  greift nur bei Apache (`AllowOverride All`); hinter nginx separat
  `location ^~ /uploads/ { deny all; }` setzen.
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
  (seit Issue #89 nur noch EINMAL in `AbstractAbrechnungModel` — die frühere
  Doppelpflege in `Ah`/`HvAbrechnungModel` ist weg und darf nicht zurückkommen).
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
  Das „Rückgängig möglich"-Flag der ÜBERSICHT kommt seit Issue #92 aus EINER
  Query (`SchuldModel::getraenkeUndoSchluessel()` — Fensterfunktion holt je
  Person den neuesten Getränke-Eintrag, purer Seam `undoSchluesselAus()`
  entscheidet per `istGetraenkeAusgleich()` und liefert ein
  `person_schluessel()`-Set); NICHT wieder `letzterGetraenkeAusgleich()` pro
  Zeile in die Schleife holen. Die Einzelvariante bleibt für die Personen-Seite.
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
- **HTTPS/Transport**: In Produktion setzt `Config\Cookie::$secure`
  (`ENVIRONMENT === 'production'`) automatisch das Secure-Flag auf Session- und
  CSRF-Cookie (Cookies gehen nie über http). HTTPS-Zwang (`app.forceGlobalSecureRequests`,
  auskommentiert in `env`) bleibt bewusst manuell — hinter einem
  TLS-terminierenden Proxy zuerst `app.proxyIPs` setzen und `X-Forwarded-Proto`
  durchreichen, sonst Redirect-Loops.
- **Beleg-Dateien**: `public/uploads/.htaccess` (`Require all denied`) muss beim
  Deploy vorhanden sein — schützt Finanzbelege vor direktem Abruf am AuthFilter
  vorbei (s. Invariante „Beleg-Dateien NIE direkt ausliefern"). Bei nginx statt
  Apache greift `.htaccess` NICHT → dort `location ^~ /uploads/ { deny all; }`.
- Nach Code-Deploy: `php spark migrate` (vorher `./scripts/backup.sh` — sichert DB-Dump
  und `public/uploads/`; Cron-Setup und Recovery-Runbook in `docs/BACKUP.md`).
- Docker-Passwörter überschreibbar via Umgebungsvariablen (`DB_PASS`,
  `MYSQL_ROOT_PASSWORD`), Defaults nur für lokale Entwicklung.
