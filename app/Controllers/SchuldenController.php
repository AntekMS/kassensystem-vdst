<?php

namespace App\Controllers;

use App\Libraries\BelegUpload;
use App\Libraries\GetraenkeRechnungImport;
use App\Libraries\RechnungPdf;
use App\Libraries\RechnungVersand;
use App\Models\AbrechnungBelegModel;
use App\Models\AhAbrechnungModel;
use App\Models\BuchungModel;
use App\Models\GetraenkeVersandModel;
use App\Models\PersonEmailModel;
use App\Models\SchuldModel;

/**
 * SchuldenController - Schuldenliste & Inventur (Issue #27)
 *
 * - Forderungen/Verbindlichkeiten pro Person erfassen (Freitext-Name)
 * - Personen-Detail als nachvollziehbare Historie (Getränkerechnungen etc.)
 * - Inventur-Export: Kassenbestand + Forderungen − Verbindlichkeiten als Excel
 */
class SchuldenController extends BaseController
{
    protected $schuldModel;

    public function __construct()
    {
        $this->schuldModel = new SchuldModel();
    }

    /**
     * Schulden-Übersicht: eine Zeile pro Person
     */
    public function index()
    {
        $inventur = $this->schuldModel->berechneInventur();

        $filter = [
            'suche' => trim((string) $this->request->getGet('suche')),
            'status' => (string) $this->request->getGet('status'),
            'sortierung' => (string) $this->request->getGet('sortierung'),
        ];

        $personen = $this->schuldModel->getPersonenUebersicht($filter);
        foreach ($personen as &$p) {
            $p['getraenke_undo'] = $this->schuldModel->letzterGetraenkeAusgleich($p['person']) !== null;
        }
        unset($p);

        $data = [
            'title' => 'Schuldenliste',
            'personen' => $personen,
            'filter' => $filter,
            'status_optionen' => [
                'offene_getraenke' => 'Offene Getränkeschulden',
                'offene_forderungen' => 'Offene Forderungen',
                'verbindlichkeiten' => 'Offene Verbindlichkeiten',
                'getraenkestopp' => 'Getränkestopp',
                'ausgeglichen' => 'Ausgeglichen',
            ],
            'sortier_optionen' => [
                'person' => 'Name (A–Z)',
                'getraenke' => 'Getränkeschulden (höchste zuerst)',
                'forderungen' => 'Forderungen (höchste zuerst)',
                'verbindlichkeiten' => 'Verbindlichkeiten (höchste zuerst)',
                'letzter_eintrag' => 'Letzter Eintrag (neueste zuerst)',
            ],
            'summe_forderungen' => $inventur['forderung']['summe'],
            'summe_verbindlichkeiten' => $inventur['verbindlichkeit']['summe'],
        ];

        return view('schulden/index', $data);
    }

    /**
     * Personen-Detail: alle Einträge einer Person (Nachverfolgung)
     *
     * Name kommt als GET-Parameter (?name=...), weil Freitext-Namen
     * Leerzeichen/Umlaute enthalten können.
     */
    public function person()
    {
        $person = trim((string) $this->request->getGet('name'));

        if ($person === '') {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Person nicht angegeben');
        }

        $eintraege = $this->schuldModel->getEintraegeFuerPerson($person);

        // Personensummen aus den Einträgen berechnen (kein extra Query nötig)
        $summen = [
            'forderungen' => 0.0,
            'forderungen_getraenke' => 0.0,
            'verbindlichkeiten' => 0.0,
        ];

        // Undo nur, solange der 1-Klick-Ausgleich der neueste Getränke-Eintrag
        // ist (Liste ist DESC-sortiert → der erste Getränke-Treffer zählt)
        $getraenkeUndo = false;
        $erster = true;

        foreach ($eintraege as $eintrag) {
            if ($eintrag['kategorie'] === 'getraenke' && $erster) {
                $getraenkeUndo = SchuldModel::istGetraenkeAusgleich($eintrag);
                $erster = false;
            }
            if ($eintrag['typ'] === 'forderung') {
                $summen['forderungen'] += (float) $eintrag['betrag'];
                if ($eintrag['kategorie'] === 'getraenke') {
                    $summen['forderungen_getraenke'] += (float) $eintrag['betrag'];
                }
            } else {
                $summen['verbindlichkeiten'] += (float) $eintrag['betrag'];
            }
        }

        $data = [
            'title' => 'Schulden: ' . $person,
            'person' => $person,
            'eintraege' => $eintraege,
            'summen' => $summen,
            'getraenke_undo' => $getraenkeUndo,
        ];

        return view('schulden/person', $data);
    }

    /**
     * Neuen Eintrag erstellen
     */
    public function create()
    {
        $data = [
            'title' => 'Neuer Schulden-Eintrag',
            'personen_namen' => $this->schuldModel->getPersonenNamen(),
            'vorauswahl_person' => trim((string) $this->request->getGet('person')),
        ];

        return view('schulden/create', $data);
    }

    /**
     * Eintrag speichern
     */
    public function store()
    {
        $daten = $this->bereiteDatenAuf($this->request->getPost());

        $fehler = $this->validiereEintrag($daten);
        if ($fehler !== null) {
            return $fehler;
        }

        if ($this->schuldModel->insert($this->extrahiereEintrag($daten))) {
            return redirect()->to('/schulden/person?name=' . urlencode($daten['person']))
                ->with('success', 'Eintrag wurde erfolgreich erstellt!');
        }

        return redirect()->back()->withInput()->with('errors', $this->schuldModel->errors());
    }

    /**
     * Eintrag bearbeiten
     */
    public function edit($id)
    {
        $eintrag = $this->schuldModel->find($id);

        if (!$eintrag) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Eintrag nicht gefunden');
        }

        if ($fehler = $this->verweigereAutomatischenEintrag($eintrag)) {
            return $fehler;
        }

        $data = [
            'title' => 'Schulden-Eintrag bearbeiten',
            'eintrag' => $eintrag,
            'personen_namen' => $this->schuldModel->getPersonenNamen(),
        ];

        return view('schulden/edit', $data);
    }

    /**
     * Eintrag aktualisieren
     */
    public function update($id)
    {
        $eintrag = $this->schuldModel->find($id);

        if (!$eintrag) {
            return redirect()->to('/schulden')->with('error', 'Eintrag nicht gefunden.');
        }

        if ($fehler = $this->verweigereAutomatischenEintrag($eintrag)) {
            return $fehler;
        }

        $daten = $this->bereiteDatenAuf($this->request->getPost());

        $fehler = $this->validiereEintrag($daten);
        if ($fehler !== null) {
            return $fehler;
        }

        if ($this->schuldModel->update($id, $this->extrahiereEintrag($daten))) {
            return redirect()->to('/schulden/person?name=' . urlencode($daten['person']))
                ->with('success', 'Eintrag wurde erfolgreich aktualisiert!');
        }

        return redirect()->back()->withInput()->with('error', 'Fehler beim Aktualisieren des Eintrags.');
    }

    /**
     * Eintrag löschen
     */
    public function delete($id)
    {
        $eintrag = $this->schuldModel->find($id);

        if (!$eintrag) {
            return redirect()->to('/schulden')->with('error', 'Eintrag nicht gefunden.');
        }

        if ($fehler = $this->verweigereAutomatischenEintrag($eintrag)) {
            return $fehler;
        }

        if ($this->schuldModel->delete($id)) {
            return redirect()->to('/schulden/person?name=' . urlencode($eintrag['person']))
                ->with('success', 'Eintrag wurde erfolgreich gelöscht!');
        }

        return redirect()->to('/schulden')->with('error', 'Fehler beim Löschen des Eintrags.');
    }

    /**
     * 1-Klick: offene Getränkerechnung einer Person begleichen (Issue #43)
     *
     * Legt einen manuellen Ausgleichs-Eintrag über die volle offene
     * Getränke-Forderung an (negativ = Rückzahlung).
     */
    public function getraenkeBeglichen()
    {
        $person = trim((string) $this->request->getPost('person'));

        if ($person === '') {
            return redirect()->to('/schulden')->with('error', 'Person nicht angegeben.');
        }

        $summe = $this->schuldModel->offeneGetraenkeForderung($person);

        if ($summe < 0.01) {
            return redirect()->back()->with('error', 'Keine offene Getränkerechnung für ' . $person . '.');
        }

        $this->schuldModel->erstelleGetraenkeAusgleich($person, $summe);

        return redirect()->back()
            ->with('success', 'Getränkerechnung von ' . $person . ' über ' . formatiere_betrag($summe) . ' beglichen.');
    }

    /**
     * 1-Klick-Undo: den zuletzt angelegten Getränkeausgleich zurücknehmen.
     *
     * Nur möglich, solange der Ausgleich der neueste Getränke-Eintrag der
     * Person ist (der Fehlklick-Fall) — danach normal löschen.
     */
    public function getraenkeBeglichenUndo()
    {
        $person = trim((string) $this->request->getPost('person'));

        if ($person === '') {
            return redirect()->to('/schulden')->with('error', 'Person nicht angegeben.');
        }

        $ausgleich = $this->schuldModel->letzterGetraenkeAusgleich($person);

        if (!$ausgleich) {
            return redirect()->back()->with('error', 'Kein rückgängig machbarer Getränkeausgleich für ' . $person . ' gefunden.');
        }

        $this->schuldModel->delete($ausgleich['id']);

        return redirect()->back()
            ->with('success', 'Getränkeausgleich von ' . $person . ' über ' . formatiere_betrag(abs((float) $ausgleich['betrag'])) . ' rückgängig gemacht.');
    }

    /**
     * Inventur-Ansicht: "Kassenwart – Aktueller Bestand" als HTML-Seite
     *
     * Gleiche Datengrundlage wie der Excel-Export (Issue #36).
     */
    public function inventur()
    {
        $kontostaende = (new BuchungModel())->berechneKontostaende();
        $inventur = $this->schuldModel->berechneInventur();

        $summeKassen = array_sum(array_column($kontostaende, 'saldo'));

        $data = [
            'title' => 'Inventur',
            'kontostaende' => $kontostaende,
            'inventur' => $inventur,
            'summe_kassen' => $summeKassen,
            'summe_gesamt' => $summeKassen
                + ($inventur['forderung']['summe'] ?? 0)
                - ($inventur['verbindlichkeit']['summe'] ?? 0),
        ];

        return view('schulden/inventur', $data);
    }

    /**
     * Inventur-Export: "Kassenwart – Aktueller Bestand" als Excel
     */
    public function exportInventur()
    {
        $kontostaende = (new BuchungModel())->berechneKontostaende();
        $inventur = $this->schuldModel->berechneInventur();

        try {
            $spreadsheet = \App\Helpers\ExcelHelper::erstelleInventur($kontostaende, $inventur);

            return \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, 'Inventur_' . date('Y-m-d') . '.xlsx');
        } catch (\Exception $e) {
            log_message('error', 'Inventur-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Inventur-Export: ' . $e->getMessage());
        }
    }

    // ==================== GETRÄNKERECHNUNG-IMPORT (Issue #35) ====================

    /**
     * Upload-Formular für die monatliche Getränkerechnung (xlsx)
     */
    public function import()
    {
        $data = [
            'title' => 'Getränkerechnung importieren',
            'max_upload_size' => BelegUpload::maxUploadSizeMb(),
            'vormonat' => date('Y-m', strtotime('first day of last month')),
        ];

        return view('schulden/import', $data);
    }

    /**
     * Schritt 1: Datei entgegennehmen, parsen und Vorschau anzeigen.
     *
     * Die Datei wird unter Zufallsnamen in writable/uploads/import/ geparkt
     * und erst beim Bestätigen (importConfirm) verarbeitet.
     */
    public function importUpload()
    {
        // Kein mime_in: Browser melden für xlsx teils application/octet-stream.
        // Die echte Formatprüfung übernimmt der PhpSpreadsheet-Load beim Parsen.
        $rules = [
            'monat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            'import_datei' => 'uploaded[import_datei]|max_size[import_datei,10240]|ext_in[import_datei,xlsx]',
        ];
        $messages = [
            'monat' => [
                'required' => 'Bitte den Monat der Rechnung angeben.',
                'regex_match' => 'Der Monat muss im Format JJJJ-MM angegeben werden.',
            ],
            'import_datei' => [
                'uploaded' => 'Bitte eine Excel-Datei auswählen.',
                'max_size' => 'Die Datei ist zu groß (maximal 10 MB).',
                'ext_in' => 'Nur .xlsx-Dateien sind erlaubt.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $monat = $this->request->getPost('monat');
        $file = $this->request->getFile('import_datei');

        if (!$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Datei-Upload: ' . $file->getErrorString());
        }

        $originalName = $file->getClientName();
        $tmpVerzeichnis = $this->importTmpVerzeichnis();
        $tmpName = bin2hex(random_bytes(16)) . '.xlsx';

        try {
            $file->move($tmpVerzeichnis, $tmpName);
            $ergebnis = (new GetraenkeRechnungImport())->parseDatei($tmpVerzeichnis . $tmpName);
        } catch (\Throwable $e) {
            @unlink($tmpVerzeichnis . $tmpName);

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        session()->set('getraenke_import', [
            'datei' => $tmpName,
            'monat' => $monat,
            'original' => $originalName,
        ]);

        return view('schulden/import_vorschau', $this->baueImportVorschau($monat, $ergebnis));
    }

    /**
     * Schritt 2: Import bestätigen — Forderungen, Belege und AH-Zuordnung anlegen.
     *
     * Liest ausschließlich die Session (kein POST-Payload außer CSRF) und
     * parst die geparkte Datei erneut.
     */
    public function importConfirm()
    {
        $import = session()->get('getraenke_import');

        if (!is_array($import) || !preg_match('/^[a-f0-9]{32}\.xlsx$/', $import['datei'] ?? '')) {
            return redirect()->to('/schulden/import')->with('error', 'Die Import-Sitzung ist abgelaufen. Bitte die Datei erneut hochladen.');
        }

        $tmpPfad = $this->importTmpVerzeichnis() . $import['datei'];
        $monat = $import['monat'];

        if (!is_file($tmpPfad)) {
            session()->remove('getraenke_import');

            return redirect()->to('/schulden/import')->with('error', 'Die hochgeladene Datei wurde nicht mehr gefunden. Bitte erneut hochladen.');
        }

        try {
            $ergebnis = (new GetraenkeRechnungImport())->parseDatei($tmpPfad);
        } catch (\RuntimeException $e) {
            @unlink($tmpPfad);
            session()->remove('getraenke_import');

            return redirect()->to('/schulden/import')->with('error', $e->getMessage());
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat);
        $datum = date('Y-m-t', strtotime($monat . '-01'));

        // 1) Forderungen als ein Batch (Transaktion): ganz oder gar nicht
        $fehler = $this->erstelleImportForderungen($ergebnis['personen'], $datum, $monatsName);
        if ($fehler !== null) {
            return $fehler;
        }

        $summe = array_sum(array_column($ergebnis['personen'], 'betrag'));
        $meldungen = [
            'Getränkerechnung ' . $monatsName . ' importiert: '
                . count($ergebnis['personen']) . ' Forderungen über ' . formatiere_betrag($summe) . ' angelegt.',
        ];

        // 2) Belege + AH-Zuordnung (nach der Transaktion — Dateioperationen);
        //    Fehler hier sind ein Teilerfolg, die Forderungen sind bereits angelegt
        $meldungen = array_merge(
            $meldungen,
            $this->erstelleImportBelege($ergebnis, $monat, $monatsName, $datum)
        );

        @unlink($tmpPfad);
        session()->remove('getraenke_import');

        return redirect()->to('/schulden/import/versand?monat=' . $monat)
            ->with('success', implode(' ', $meldungen));
    }

    /**
     * Schritt 3: Versand-Seite — Personen des Monats mit Betrag, E-Mail-Adresse
     * und Auswahl. Datenquelle sind die importierten Forderungen (exakter
     * grund-Match), daher jederzeit erneut aufrufbar.
     */
    public function importVersand()
    {
        $monat = (string) $this->request->getGet('monat');

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat);
        $personen = $this->schuldModel->getImportForderungen($monatsName);

        if ($personen === []) {
            return redirect()->to('/schulden/import')
                ->with('error', 'Für ' . $monatsName . ' wurden keine importierten Getränke-Forderungen gefunden.');
        }

        $emails = (new PersonEmailModel())->findEmailsFuer(array_column($personen, 'person'));
        $versendet = (new GetraenkeVersandModel())->getVersendetFuerMonat($monat);

        foreach ($personen as &$person) {
            $key = mb_strtolower($person['person']);
            $person['email'] = $emails[$key] ?? '';
            $person['versendet_am'] = $versendet[$key] ?? null;
        }
        unset($person);

        $data = [
            'title' => 'Rechnungsversand: Getränkerechnung ' . $monatsName,
            'monat' => $monat,
            'monats_name' => $monatsName,
            'personen' => $personen,
            'smtp_ok' => RechnungVersand::istKonfiguriert(),
            'versand_ergebnisse' => session()->getFlashdata('versand_ergebnisse') ?? [],
        ];

        return view('schulden/import_versand', $data);
    }

    /**
     * Versand ausführen: Adressen speichern (immer) und an die ausgewählten
     * Personen die Einzelrechnung als PDF mailen. POST liefert nur Auswahl
     * und Adressen — Personen und Beträge kommen aus der DB (Whitelist).
     */
    public function importVersandSenden()
    {
        $monat = (string) $this->request->getPost('monat');

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat);
        $forderungen = [];
        foreach ($this->schuldModel->getImportForderungen($monatsName) as $zeile) {
            $forderungen[mb_strtolower($zeile['person'])] = $zeile;
        }

        if ($forderungen === []) {
            return redirect()->to('/schulden/import')
                ->with('error', 'Für ' . $monatsName . ' wurden keine importierten Getränke-Forderungen gefunden.');
        }

        // 1) Adressen speichern — auch ohne Versand (z.B. SMTP fehlt)
        $emailModel = new PersonEmailModel();
        $fehler = [];
        $adressen = [];

        foreach ((array) $this->request->getPost('email') as $person => $email) {
            $key = mb_strtolower(trim((string) $person));
            $email = trim((string) $email);

            if (!isset($forderungen[$key]) || $email === '') {
                continue;
            }

            if ($emailModel->upsertEmail($forderungen[$key]['person'], $email)) {
                $adressen[$key] = $email;
            } else {
                $fehler[] = $forderungen[$key]['person'] . ': ' . implode(' ', $emailModel->errors());
            }
        }

        if ($fehler !== []) {
            return redirect()->to('/schulden/import/versand?monat=' . $monat)
                ->with('error', 'Nicht alle Adressen konnten gespeichert werden. ' . implode(' ', $fehler));
        }

        if ($this->request->getPost('nur_speichern') !== null || !RechnungVersand::istKonfiguriert()) {
            return redirect()->to('/schulden/import/versand?monat=' . $monat)
                ->with('success', 'E-Mail-Adressen wurden gespeichert.');
        }

        // 2) Versand an die ausgewählten Personen
        $versand = new RechnungVersand();
        $rechnungPdf = new RechnungPdf();
        $versandLog = new GetraenkeVersandModel();
        $ergebnisse = [];

        foreach ((array) $this->request->getPost('senden') as $person) {
            $key = mb_strtolower(trim((string) $person));

            if (!isset($forderungen[$key])) {
                continue;
            }

            $name = $forderungen[$key]['person'];
            $betrag = (float) $forderungen[$key]['betrag'];
            $email = $adressen[$key] ?? '';

            if ($email === '') {
                $ergebnisse[] = ['person' => $name, 'email' => '', 'ok' => false, 'hinweis' => 'keine E-Mail-Adresse'];
                continue;
            }

            $pdf = $rechnungPdf->einzel($name, $monatsName, $betrag, date('Y-m-d'));
            $mail = RechnungVersand::baueMail($name, $monatsName, $betrag);
            $ok = $versand->sende($email, $mail['betreff'], $mail['text'], $pdf, RechnungPdf::dateiname($monatsName, $name));

            if ($ok) {
                $versandLog->logVersand($monat, $name, $email);
            }

            $ergebnisse[] = [
                'person' => $name,
                'email' => $email,
                'ok' => $ok,
                'hinweis' => $ok ? '' : 'Versand fehlgeschlagen (Details im Log)',
            ];
        }

        if ($ergebnisse === []) {
            return redirect()->to('/schulden/import/versand?monat=' . $monat)
                ->with('success', 'E-Mail-Adressen wurden gespeichert — es war keine Person zum Versand ausgewählt.');
        }

        // PRG: Redirect verhindert Doppelversand per Browser-Reload
        return redirect()->to('/schulden/import/versand?monat=' . $monat)
            ->with('versand_ergebnisse', $ergebnisse);
    }

    /**
     * Übersichts-Rechnung des Monats als PDF (für den Aushang)
     */
    public function importUebersichtPdf()
    {
        $monat = (string) $this->request->getGet('monat');

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat);
        $personen = $this->schuldModel->getImportForderungen($monatsName);

        if ($personen === []) {
            return redirect()->to('/schulden/import')
                ->with('error', 'Für ' . $monatsName . ' wurden keine importierten Getränke-Forderungen gefunden.');
        }

        try {
            $pdf = (new RechnungPdf())->uebersicht($monatsName, $personen, date('Y-m-d'));

            return $this->response
                ->download(RechnungPdf::dateiname($monatsName, 'Uebersicht'), $pdf)
                ->setContentType('application/pdf');
        } catch (\Throwable $e) {
            log_message('error', 'Übersichts-PDF Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Erzeugen des PDFs: ' . $e->getMessage());
        }
    }

    // ==================== E-MAIL-ADRESSEN (Issue #35) ====================

    /**
     * Verwaltung der Personen-E-Mail-Adressen
     */
    public function emails()
    {
        $data = [
            'title' => 'E-Mail-Adressen',
            'eintraege' => (new PersonEmailModel())->getAlle(),
            'personen_namen' => $this->schuldModel->getPersonenNamen(),
        ];

        return view('schulden/emails', $data);
    }

    /**
     * Adresse anlegen bzw. aktualisieren (Upsert über den Namen)
     */
    public function emailsStore()
    {
        $name = trim((string) $this->request->getPost('name'));
        $email = trim((string) $this->request->getPost('email'));

        $emailModel = new PersonEmailModel();

        if (!$emailModel->upsertEmail($name, $email)) {
            return redirect()->back()->withInput()->with('errors', $emailModel->errors());
        }

        return redirect()->to('/schulden/emails')->with('success', 'E-Mail-Adresse für ' . $name . ' gespeichert.');
    }

    /**
     * Adresse löschen
     */
    public function emailsDelete($id)
    {
        $emailModel = new PersonEmailModel();
        $eintrag = $emailModel->find($id);

        if (!$eintrag) {
            return redirect()->to('/schulden/emails')->with('error', 'Eintrag nicht gefunden.');
        }

        $emailModel->delete($id);

        return redirect()->to('/schulden/emails')->with('success', 'E-Mail-Adresse von ' . $eintrag['name'] . ' gelöscht.');
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Tmp-Verzeichnis für geparkte Import-Dateien; räumt Altlasten (>24h) weg.
     */
    private function importTmpVerzeichnis(): string
    {
        $verzeichnis = WRITEPATH . 'uploads/import/';

        if (!is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0755, true);
        }

        // xlsx = geparkte Uploads, pdf = verwaiste Temp-Rechnungen
        foreach (array_merge(glob($verzeichnis . '*.xlsx') ?: [], glob($verzeichnis . '*.pdf') ?: []) as $datei) {
            if (filemtime($datei) < time() - 86400) {
                @unlink($datei);
            }
        }

        return $verzeichnis;
    }

    /**
     * Baut die View-Daten für die Import-Vorschau (inkl. Warnungen).
     */
    private function baueImportVorschau(string $monat, array $ergebnis): array
    {
        $monatsName = GetraenkeRechnungImport::monatsName($monat);
        $grund = SchuldModel::getraenkeImportGrund($monatsName);

        $bekannteNamen = array_map('mb_strtolower', $this->schuldModel->getPersonenNamen());
        foreach ($ergebnis['personen'] as &$person) {
            $person['bekannt'] = in_array(mb_strtolower($person['person']), $bekannteNamen, true);
        }
        unset($person);

        $warnungen = [];

        $vorhandene = $this->schuldModel->where('grund', $grund)->countAllResults();
        if ($vorhandene > 0) {
            $warnungen[] = 'Es existieren bereits ' . $vorhandene . ' Einträge mit dem Grund „' . $grund
                . '" — diese Rechnung wurde möglicherweise schon importiert.';
        }

        foreach (['coleur' => 'Coleur', 'bund' => 'Bund'] as $key => $label) {
            if ($ergebnis[$key] === null) {
                $warnungen[] = 'Die ' . $label . '-Summe wurde in der Datei nicht gefunden — es wird kein ' . $label . '-Beleg angelegt.';
            } elseif ($ergebnis[$key] <= 0) {
                $warnungen[] = 'Die ' . $label . '-Summe ist 0 — es wird kein ' . $label . '-Beleg angelegt.';
            }
        }

        $abrechnungModel = new AhAbrechnungModel();
        $offene = $abrechnungModel->findeOffeneAbrechnung();
        $zielAbrechnung = null;

        if ($offene !== null) {
            $zielAbrechnung = $offene['titel'];
        } elseif ($abrechnungModel->abrechnungsmonatExistiert($monat)) {
            $warnungen[] = 'Es gibt keine offene AH-Abrechnung und der Monat ' . $monatsName
                . ' ist bereits abgerechnet — die Belege werden keiner Abrechnung zugeordnet.';
        } else {
            $zielAbrechnung = 'AH² Abrechnung ' . $monatsName . ' (wird neu angelegt)';
        }

        return [
            'title' => 'Import-Vorschau: Getränkerechnung ' . $monatsName,
            'monat' => $monat,
            'monats_name' => $monatsName,
            'personen' => $ergebnis['personen'],
            'summe' => array_sum(array_column($ergebnis['personen'], 'betrag')),
            'coleur' => $ergebnis['coleur'],
            'bund' => $ergebnis['bund'],
            'ziel_abrechnung' => $zielAbrechnung,
            'warnungen' => $warnungen,
        ];
    }

    /**
     * Legt die Getränke-Forderungen als Batch in einer Transaktion an.
     * Gibt bei Fehlern eine Redirect-Response zurück, sonst null.
     */
    private function erstelleImportForderungen(array $personen, string $datum, string $monatsName)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($personen as $person) {
            $ok = $this->schuldModel->insert([
                'person' => $person['person'],
                'typ' => 'forderung',
                'kategorie' => 'getraenke',
                'datum' => $datum,
                'grund' => SchuldModel::getraenkeImportGrund($monatsName),
                'betrag' => number_format($person['betrag'], 2, '.', ''),
            ]);

            if (!$ok) {
                $db->transRollback();

                return redirect()->to('/schulden/import')->with(
                    'error',
                    'Import abgebrochen — Eintrag für „' . $person['person'] . '" konnte nicht angelegt werden: '
                        . implode(' ', $this->schuldModel->errors())
                );
            }
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return redirect()->to('/schulden/import')->with('error', 'Import fehlgeschlagen — es wurden keine Einträge angelegt.');
        }

        return null;
    }

    /**
     * Legt die Coleur-/Bund-Belege als generierte PDF-Rechnungen an und
     * ordnet sie der offenen AH-Abrechnung zu. Gibt Meldungs-Sätze für die
     * Success-Message zurück.
     */
    private function erstelleImportBelege(array $ergebnis, string $monat, string $monatsName, string $datum): array
    {
        $belegIds = [];
        $meldungen = [];
        $upload = new BelegUpload();
        $rechnungPdf = new RechnungPdf();

        foreach (['coleur' => 'Coleur', 'bund' => 'Bund'] as $key => $label) {
            if ($ergebnis[$key] === null || $ergebnis[$key] <= 0) {
                continue;
            }

            // PDF in eine Temp-Datei, weil speichereBelegAusDatei von einer
            // serverseitig liegenden Quelle kopiert
            $tmpPdf = $this->importTmpVerzeichnis() . uniqid('rechnung_', true) . '.pdf';

            try {
                file_put_contents($tmpPdf, $rechnungPdf->coleurBund($label, $monatsName, (float) $ergebnis[$key], $datum));

                $belegIds[] = $upload->speichereBelegAusDatei($tmpPdf, RechnungPdf::dateiname($monatsName, $label), [
                    'rechnungsdatum' => $datum,
                    'beschreibung' => 'Getränkerechnung ' . $monatsName . ' – ' . $label,
                    'betrag' => number_format($ergebnis[$key], 2, '.', ''),
                    'kategorie' => 'ah_berechtigt',
                ]);
            } catch (\Throwable $e) {
                $meldungen[] = 'Der ' . $label . '-Beleg konnte nicht angelegt werden: ' . $e->getMessage();
            } finally {
                @unlink($tmpPdf);
            }
        }

        if ($belegIds === []) {
            return $meldungen;
        }

        $abrechnungModel = new AhAbrechnungModel();
        $abrechnung = $abrechnungModel->findeOffeneAbrechnung();

        if ($abrechnung === null) {
            $neueId = $abrechnungModel->erstelleAbrechnung(['abrechnungsmonat' => $monat]);
            $abrechnung = $neueId ? $abrechnungModel->find($neueId) : null;
        }

        if ($abrechnung === null) {
            $meldungen[] = count($belegIds) . ' Belege wurden erstellt, konnten aber keiner AH-Abrechnung zugeordnet werden — bitte manuell zuordnen.';

            return $meldungen;
        }

        // Jeder Beleg einzeln durch fuegeZuordnungHinzu (Guards bleiben aktiv),
        // berechneGesamtsumme genau EINMAL nach dem Batch
        $zugeordnet = 0;
        $zuordnungModel = new AbrechnungBelegModel();

        foreach ($belegIds as $belegId) {
            if ($zuordnungModel->fuegeZuordnungHinzu($belegId, 'ah', $abrechnung['id'])) {
                $zugeordnet++;
            }
        }

        if ($zugeordnet > 0) {
            $abrechnungModel->berechneGesamtsumme($abrechnung['id']);
        }

        $meldungen[] = count($belegIds) . ' Belege erstellt'
            . ($zugeordnet > 0
                ? ' und der Abrechnung „' . $abrechnung['titel'] . '" zugeordnet.'
                : ', die Zuordnung zur Abrechnung „' . $abrechnung['titel'] . '" schlug fehl — bitte manuell zuordnen.');

        return $meldungen;
    }

    /**
     * Automatische Einträge (aus Beleg/Buchung/Abrechnung) sind manuell
     * nicht änderbar — sie werden über ihre Quelle gepflegt (Issue #38).
     * Gibt bei so einem Eintrag eine Redirect-Response zurück, sonst null.
     */
    private function verweigereAutomatischenEintrag(array $eintrag)
    {
        if (!SchuldModel::istAutomatisch($eintrag)) {
            return null;
        }

        return redirect()->to('/schulden/person?name=' . urlencode($eintrag['person']))
            ->with('error', 'Dieser Eintrag wird automatisch über Beleg, Buchung bzw. Abrechnung verwaltet. Bitte die Quelle bearbeiten oder löschen.');
    }

    /**
     * Normalisiert POST-Daten (Name trimmen, Betrag normalisieren)
     */
    private function bereiteDatenAuf(array $daten): array
    {
        $daten['person'] = trim(preg_replace('/\s+/', ' ', $daten['person'] ?? ''));
        $daten['betrag'] = normalisiere_betrag($daten['betrag'] ?? null);

        return $daten;
    }

    /**
     * Validiert einen Eintrag; gibt bei Fehlern eine Redirect-Response zurück
     */
    private function validiereEintrag(array $daten)
    {
        $validation = \Config\Services::validation();
        $validation->setRules($this->schuldModel->getValidationRules(), $this->schuldModel->getValidationMessages());

        if (!$validation->run($daten)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        if ((float) $daten['betrag'] === 0.0) {
            return redirect()->back()->withInput()->with('errors', ['betrag' => 'Der Betrag darf nicht 0 sein.']);
        }

        return null;
    }

    /**
     * Extrahiert die erlaubten Eintrag-Felder aus den POST-Daten
     */
    private function extrahiereEintrag(array $daten): array
    {
        return [
            'person' => $daten['person'],
            'typ' => $daten['typ'] ?? 'forderung',
            'kategorie' => $daten['kategorie'] ?? 'getraenke',
            'datum' => $daten['datum'],
            'grund' => $daten['grund'],
            'betrag' => $daten['betrag'],
        ];
    }
}
