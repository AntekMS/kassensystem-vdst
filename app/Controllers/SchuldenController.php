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
use App\Models\PersonModel;
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
            'personen_namen' => (new PersonModel())->getAnzeigenamen(),
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
            'personen_namen' => (new PersonModel())->getAnzeigenamen(),
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

        return $this->beglichenRedirect($person)
            ->with('success', 'Getränkerechnung von ' . $person . ' über ' . formatiere_betrag($summe) . ' beglichen.');
    }

    /**
     * 1-Klick: offene Getränkerechnungen ALLER Personen begleichen (Issue #55).
     *
     * Legt je Person mit offenem Getränke-Restbetrag denselben Ausgleich an
     * wie der Einzel-Button (SchuldModel::begleicheAlleGetraenke ruft pro Person
     * erstelleGetraenkeAusgleich). Bewusst ohne JS-Confirm, konsistent mit den
     * übrigen 1-Klick-Buttons; einzeln über „Rückgängig" bzw. Löschen umkehrbar.
     */
    public function getraenkeAlleBeglichen()
    {
        $anzahl = $this->schuldModel->begleicheAlleGetraenke();

        if ($anzahl === 0) {
            return redirect()->to('/schulden')->with('error', 'Es gibt keine offenen Getränkerechnungen zum Begleichen.');
        }

        // Massen-Aktion: kein Personen-Anker, Sprung an den Listenanfang genügt.
        return redirect()->to('/schulden')
            ->with('success', $anzahl . ($anzahl === 1 ? ' offene Getränkerechnung' : ' offene Getränkerechnungen') . ' beglichen.');
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

        return $this->beglichenRedirect($person)
            ->with('success', 'Getränkeausgleich von ' . $person . ' über ' . formatiere_betrag(abs((float) $ausgleich['betrag'])) . ' rückgängig gemacht.');
    }

    /**
     * Redirect nach einer 1-Klick-Getränkeaktion (Issue #55): zurück zur
     * Herkunftsseite (Übersicht ODER Personen-Detail), aber mit Personen-Anker,
     * damit der Browser an der jeweiligen Zeile stehen bleibt statt nach oben
     * zu springen. Der Anker existiert nur auf der Übersicht als `<tr id>` — auf
     * der (kurzen) Personen-Seite ist er wirkungslos und stört nicht.
     *
     * SICHERHEIT: previous_url() fällt bei fehlender Session-URL auf den
     * angreiferbeeinflussbaren HTTP_REFERER zurück und redirect()->to() folgt
     * absoluten scheme://-Zielen → Open-Redirect-Gefahr. Deshalb wird nur ein
     * same-site-Ziel akzeptiert (Host == base_url-Host); von jedem Ziel wird
     * ohnehin nur Path+Query übernommen, fremder/leerer Host fällt auf
     * /schulden zurück.
     */
    private function beglichenRedirect(string $person)
    {
        helper('url');

        $eigenerHost = (string) parse_url(base_url(), PHP_URL_HOST);
        $ziel = self::sameSiteRuecksprungPfad(previous_url(), $eigenerHost);

        return redirect()->to($ziel . '#' . person_anker($person));
    }

    /**
     * Same-site-Guard gegen Open-Redirect (Issue #55): übernimmt vom Kandidaten
     * NUR Path+Query und auch das nur, wenn dessen Host dem eigenen base_url()-
     * Host entspricht — sonst der sichere Default /schulden. Scheme, Host und
     * ein evtl. Fragment des Kandidaten werden verworfen; ein fehlender/fremder
     * Host (inkl. protokoll-relativer //evil.com-Tricks) fällt auf den Default
     * zurück. Pur (parse_url, keine Services) → direkt unit-testbar.
     */
    public static function sameSiteRuecksprungPfad(?string $kandidat, string $eigenerHost): string
    {
        $default = '/schulden';

        if (!is_string($kandidat) || trim($kandidat) === '') {
            return $default;
        }

        $teile = parse_url($kandidat);
        if ($teile === false) {
            return $default;
        }

        $zielHost = strtolower((string) ($teile['host'] ?? ''));
        $eigenerHost = strtolower(trim($eigenerHost));

        // Fremder oder leerer Host → nicht folgen (Path-only-Referer inklusive).
        if ($zielHost === '' || $eigenerHost === '' || $zielHost !== $eigenerHost) {
            return $default;
        }

        $pfad = '/' . ltrim((string) ($teile['path'] ?? ''), '/');

        return isset($teile['query']) && $teile['query'] !== ''
            ? $pfad . '?' . $teile['query']
            : $pfad;
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
        // 'monat' ist bewusst NICHT mehr required (Issue #57): bleibt das Feld
        // leer, wird der Monat aus dem Dateinamen abgeleitet bzw. der Vormonat
        // verwendet — eine ausdrückliche Eingabe hat aber immer Vorrang.
        $rules = [
            'monat' => 'permit_empty|regex_match[/^\d{4}-\d{2}$/]',
            'monat_bis' => 'permit_empty|regex_match[/^\d{4}-\d{2}$/]',
            'import_datei' => 'uploaded[import_datei]|max_size[import_datei,10240]|ext_in[import_datei,xlsx]',
        ];
        $messages = [
            'monat' => [
                'regex_match' => 'Der Monat muss im Format JJJJ-MM angegeben werden.',
            ],
            'monat_bis' => [
                'regex_match' => 'Der Endmonat muss im Format JJJJ-MM angegeben werden.',
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

        $file = $this->request->getFile('import_datei');

        if (!$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Datei-Upload: ' . $file->getErrorString());
        }

        $originalName = $file->getClientName();

        $monatEingabe = trim((string) $this->request->getPost('monat'));
        $monatBisEingabe = trim((string) $this->request->getPost('monat_bis'));
        $monatBis = $monatBisEingabe !== '' ? $monatBisEingabe : null;

        // Vorschlag NUR, wenn der Nutzer das Feld leer gelassen hat — eine
        // ausdrückliche Eingabe wird nie überschrieben.
        $ausDateiname = GetraenkeRechnungImport::monatAusDateiname($originalName);
        $monatAusDateinameUebernommen = $monatEingabe === '' && $ausDateiname !== null;
        $monat = $monatEingabe !== ''
            ? $monatEingabe
            : ($ausDateiname ?? date('Y-m', strtotime('first day of last month')));

        if ($monatBis !== null && $monatBis < $monat) {
            return redirect()->back()->withInput()->with('errors', ['monat_bis' => 'Der Endmonat darf nicht vor dem Startmonat liegen.']);
        }

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
            'monat_bis' => $monatBis,
            'original' => $originalName,
        ]);

        return view(
            'schulden/import_vorschau',
            $this->baueImportVorschau($monat, $ergebnis, $monatBis, $monatAusDateinameUebernommen)
        );
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
        $monatBis = $import['monat_bis'] ?? null;

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

        $monatsName = GetraenkeRechnungImport::monatsName($monat, $monatBis);
        // Rechnungsdatum: letzter Tag des (End-)Monats — bei einem Zeitraum
        // der letzte Tag des Endmonats.
        $datum = date('Y-m-t', strtotime(($monatBis ?? $monat) . '-01'));

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

        return redirect()->to('/schulden/import/versand?' . $this->versandQuery($monat, $monatBis))
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
        $monatBis = $this->leseMonatBis((string) $this->request->getGet('bis'));

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }
        if ($monatBis === false) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Endmonat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat, $monatBis);
        $personen = $this->schuldModel->getImportForderungen($monatsName);

        if ($personen === []) {
            // Kommt der Aufruf direkt nach einem Import (Erfolgs-Flash gesetzt),
            // war der Import erfolgreich — nur gibt es nichts zu versenden. Die
            // Erfolgsmeldung nicht verschlucken, sonst wirkt der Import gescheitert.
            $erfolg = session()->getFlashdata('success');

            return redirect()->to('/schulden/import')
                ->with(
                    $erfolg ? 'success' : 'error',
                    $erfolg
                        ? $erfolg . ' Keine offenen Getränke-Forderungen zum Versand für ' . $monatsName . '.'
                        : 'Für ' . $monatsName . ' wurden keine importierten Getränke-Forderungen gefunden.'
                );
        }

        $emails = (new PersonModel())->findEmailsFuer(array_column($personen, 'person'));
        $versendet = (new GetraenkeVersandModel())->getVersendetFuerMonat($monat);

        foreach ($personen as &$person) {
            $key = person_schluessel($person['person']);
            $person['email'] = $emails[$key] ?? '';
            $person['versendet_am'] = $versendet[$key] ?? null;
        }
        unset($person);

        $data = [
            'title' => 'Rechnungsversand: Getränkerechnung ' . $monatsName,
            'monat' => $monat,
            'monat_bis' => $monatBis,
            'monats_name' => $monatsName,
            'personen' => $personen,
            'smtp_ok' => RechnungVersand::istKonfiguriert(),
            'versand_ergebnisse' => session()->getFlashdata('versand_ergebnisse') ?? [],
            'frist_default' => $this->berechneFristDefault(),
        ];

        return view('schulden/import_versand', $data);
    }

    /**
     * Default-Rückmeldefrist (Issue #60): der Freitag dieser Woche; liegt der
     * bereits in der Vergangenheit (Aufruf z.B. am Wochenende), der nächste
     * Freitag. Bewusst über date('N') (ISO-Wochentag) statt über
     * strtotime('friday this week')-Relativformate berechnet, deren Verhalten
     * an Wochentag-Grenzen nicht eindeutig ist.
     */
    private function berechneFristDefault(): string
    {
        $heute = new \DateTime('today');
        $isoTag = (int) $heute->format('N'); // 1 = Montag … 7 = Sonntag
        $freitag = (clone $heute)->modify((5 - $isoTag) . ' days');

        if ($freitag < $heute) {
            $freitag->modify('+7 days');
        }

        return $freitag->format('Y-m-d');
    }

    /**
     * Strenge Y-m-d-Prüfung (Regex + Kalender-Validität) für die per POST
     * gelieferte Rückmeldefrist. Ein bereits vergangenes Datum gilt als
     * ungültig — es soll nie eine schon abgelaufene Frist verschickt werden
     * (Aufrufer fällt dann auf berechneFristDefault() zurück).
     */
    private function istGueltigesDatum(string $wert): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return false;
        }

        [$jahr, $monat, $tag] = array_map('intval', explode('-', $wert));

        if (!checkdate($monat, $tag, $jahr)) {
            return false;
        }

        return new \DateTime($wert) >= new \DateTime('today');
    }

    /**
     * Versand ausführen: Adressen speichern (immer) und an die ausgewählten
     * Personen die Einzelrechnung als PDF mailen. POST liefert nur Auswahl
     * und Adressen — Personen und Beträge kommen aus der DB (Whitelist).
     */
    public function importVersandSenden()
    {
        $monat = (string) $this->request->getPost('monat');
        $monatBis = $this->leseMonatBis((string) $this->request->getPost('monat_bis'));

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }
        if ($monatBis === false) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Endmonat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat, $monatBis);
        $forderungen = [];
        foreach ($this->schuldModel->getImportForderungen($monatsName) as $zeile) {
            $forderungen[person_schluessel($zeile['person'])] = $zeile;
        }

        if ($forderungen === []) {
            return redirect()->to('/schulden/import')
                ->with('error', 'Für ' . $monatsName . ' wurden keine importierten Getränke-Forderungen gefunden.');
        }

        // POST ist index-basiert: person[i] trägt den (Freitext-)Namen, damit
        // Namen mit '[' / ']' die PHP-Array-Key-Zerlegung nicht zerbrechen.
        $namenNachIndex = (array) $this->request->getPost('person');

        // 1) Adressen speichern — auch ohne Versand (z.B. SMTP fehlt)
        $personModel = new PersonModel();
        $fehler = [];

        foreach ((array) $this->request->getPost('email') as $index => $email) {
            $key = person_schluessel((string) ($namenNachIndex[$index] ?? ''));
            $email = trim((string) $email);

            if (!isset($forderungen[$key]) || $email === '') {
                continue;
            }

            if ($personModel->upsertFuerName($forderungen[$key]['person'], $email) === null) {
                $fehler[] = $forderungen[$key]['person'] . ': ' . implode(' ', $personModel->errors());
            }
        }

        if ($fehler !== []) {
            return redirect()->to('/schulden/import/versand?' . $this->versandQuery($monat, $monatBis))
                ->with('error', 'Nicht alle Adressen konnten gespeichert werden. ' . implode(' ', $fehler));
        }

        if ($this->request->getPost('nur_speichern') !== null || !RechnungVersand::istKonfiguriert()) {
            return redirect()->to('/schulden/import/versand?' . $this->versandQuery($monat, $monatBis))
                ->with('success', 'E-Mail-Adressen wurden gespeichert.');
        }

        // Adressen aus der DB (Whitelist der Forderungs-Namen), nicht aus dem
        // POST — deckt auch bereits gespeicherte Adressen ab, deren Feld leer
        // gepostet wurde.
        $adressen = $personModel->findEmailsFuer(array_column($forderungen, 'person'));

        // Rückmelde-Frist aus dem POST — ungültig/leer fällt auf den
        // berechneten Default zurück (kein harter Fehler, das Formular ist
        // schon mit dem Default vorbelegt).
        $fristPost = (string) $this->request->getPost('frist');
        $frist = $this->istGueltigesDatum($fristPost) ? $fristPost : $this->berechneFristDefault();

        // 2) Versand an die ausgewählten Personen
        $versand = new RechnungVersand();
        $rechnungPdf = new RechnungPdf();
        $versandLog = new GetraenkeVersandModel();
        $ergebnisse = [];

        foreach ((array) $this->request->getPost('senden') as $index) {
            $key = person_schluessel((string) ($namenNachIndex[$index] ?? ''));

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

            // PDF-Erzeugung (dompdf) kann werfen — ein Fehler bei Person N darf
            // nicht den ganzen POST abbrechen (sonst Teil-Versand ohne PRG,
            // Reload würde erneut senden).
            try {
                $pdf = $rechnungPdf->einzel($name, $monatsName, $betrag, date('Y-m-d'));
                $mail = RechnungVersand::baueMail($name, $monatsName, $frist);
                $ok = $versand->sende($email, $mail['betreff'], $mail['text'], $pdf, RechnungPdf::dateiname($monatsName, $name));
            } catch (\Throwable $e) {
                log_message('error', 'Rechnungsversand fehlgeschlagen (' . $name . '): ' . $e->getMessage());
                $ergebnisse[] = ['person' => $name, 'email' => $email, 'ok' => false, 'hinweis' => 'Fehler beim Erzeugen der Rechnung'];
                continue;
            }

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
            return redirect()->to('/schulden/import/versand?' . $this->versandQuery($monat, $monatBis))
                ->with('success', 'E-Mail-Adressen wurden gespeichert — es war keine Person zum Versand ausgewählt.');
        }

        // PRG: Redirect verhindert Doppelversand per Browser-Reload
        return redirect()->to('/schulden/import/versand?' . $this->versandQuery($monat, $monatBis))
            ->with('versand_ergebnisse', $ergebnisse);
    }

    /**
     * Übersichts-Rechnung des Monats als PDF (für den Aushang)
     */
    public function importUebersichtPdf()
    {
        $monat = (string) $this->request->getGet('monat');
        $monatBis = $this->leseMonatBis((string) $this->request->getGet('bis'));

        if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Monat angegeben.');
        }
        if ($monatBis === false) {
            return redirect()->to('/schulden/import')->with('error', 'Kein gültiger Endmonat angegeben.');
        }

        $monatsName = GetraenkeRechnungImport::monatsName($monat, $monatBis);
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

    // ==================== PERSONEN-VERWALTUNG (Issue #61) ====================

    /**
     * Verwaltung des Personen-Registers (Vor-/Nachname, E-Mail, aktiv).
     * Löst die frühere reine E-Mail-Adressverwaltung ab. Optional ?edit=ID
     * blendet den Bearbeiten-Modus für einen Eintrag im Formular ein.
     */
    public function personen()
    {
        $personModel = new PersonModel();

        $editId = (int) $this->request->getGet('edit');
        $bearbeiten = $editId > 0 ? $personModel->find($editId) : null;

        $data = [
            'title' => 'Personen',
            'eintraege' => $personModel->getAlle(),
            'bearbeiten' => $bearbeiten,
        ];

        return view('schulden/personen', $data);
    }

    /**
     * Person anlegen (kein id) bzw. aktualisieren (id vorhanden).
     */
    public function personenStore()
    {
        $personModel = new PersonModel();

        $id = (int) $this->request->getPost('id');
        $daten = [
            'vorname' => person_normalisiere((string) $this->request->getPost('vorname')),
            'nachname' => person_normalisiere((string) $this->request->getPost('nachname')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'aktiv' => $this->request->getPost('aktiv') !== null ? 1 : 0,
        ];

        $ok = $id > 0 ? $personModel->update($id, $daten) : $personModel->insert($daten);

        if (!$ok) {
            return redirect()->back()->withInput()->with('errors', $personModel->errors());
        }

        $anzeige = PersonModel::anzeigename($daten);

        return redirect()->to('/schulden/personen')
            ->with('success', 'Person ' . $anzeige . ' gespeichert.');
    }

    /**
     * Person löschen. Verknüpfte schulden-Einträge behalten ihren Namen
     * (person_id → NULL per FK ON DELETE SET NULL), der Ledger bleibt intakt.
     */
    public function personenDelete($id)
    {
        $personModel = new PersonModel();
        $eintrag = $personModel->find($id);

        if (!$eintrag) {
            return redirect()->to('/schulden/personen')->with('error', 'Person nicht gefunden.');
        }

        $personModel->delete($id);

        return redirect()->to('/schulden/personen')
            ->with('success', 'Person ' . PersonModel::anzeigename($eintrag) . ' gelöscht.');
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
     * Baut die View-Daten für die Import-Vorschau (inkl. Warnungen und Hinweisen).
     *
     * $monatAusDateinameUebernommen zeigt an, dass der Monat NICHT explizit
     * eingegeben, sondern aus dem Original-Dateinamen abgeleitet wurde
     * (Issue #57) — wird nur als Hinweis angezeigt, nicht als Warnung.
     */
    private function baueImportVorschau(
        string $monat,
        array $ergebnis,
        ?string $monatBis = null,
        bool $monatAusDateinameUebernommen = false
    ): array {
        $monatsName = GetraenkeRechnungImport::monatsName($monat, $monatBis);
        $grund = SchuldModel::getraenkeImportGrund($monatsName);

        // „Bekannt" = im Personen-Register vorhanden (Match über person_schluessel).
        // #62 nutzt dieses Register für die Nachname→Vollname-Auflösung.
        $register = (new PersonModel())->alleMitSchluessel();
        foreach ($ergebnis['personen'] as &$person) {
            $person['bekannt'] = isset($register[person_schluessel($person['person'])]);
        }
        unset($person);

        $hinweise = [];
        if ($monatAusDateinameUebernommen) {
            $hinweise[] = 'Der Monat „' . GetraenkeRechnungImport::monatsName($monat)
                . '" wurde aus dem Dateinamen übernommen (kein Monat eingegeben) — bitte prüfen und beim erneuten '
                . 'Hochladen ggf. explizit angeben.';
        }

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
            'monat_bis' => $monatBis,
            'monats_name' => $monatsName,
            'personen' => $ergebnis['personen'],
            'summe' => array_sum(array_column($ergebnis['personen'], 'betrag')),
            'coleur' => $ergebnis['coleur'],
            'bund' => $ergebnis['bund'],
            'ziel_abrechnung' => $zielAbrechnung,
            'hinweise' => $hinweise,
            'warnungen' => $warnungen,
        ];
    }

    /**
     * Liefert den Query-String für die Versand-/Übersichts-URLs eines Monats
     * bzw. Zeitraums (Issue #57) — einzige Stelle, die "monat"/"bis" zu einem
     * Query-String zusammensetzt, damit beide Parameter überall konsistent
     * durchgereicht werden.
     */
    private function versandQuery(string $monat, ?string $monatBis): string
    {
        return 'monat=' . $monat . ($monatBis !== null ? '&bis=' . $monatBis : '');
    }

    /**
     * Liest einen optionalen Endmonat-Parameter ("bis"): null, wenn leer,
     * der Wert bei gültigem JJJJ-MM-Format, sonst false (ungültig).
     *
     */
    private function leseMonatBis(string $wert): string|false|null
    {
        if (trim($wert) === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}$/', $wert) === 1 ? $wert : false;
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
                'person_id' => (new PersonModel())->findIdFuerName($person['person']),
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
                if (file_put_contents($tmpPdf, $rechnungPdf->coleurBund($label, $monatsName, (float) $ergebnis[$key], $datum)) === false) {
                    throw new \RuntimeException('PDF-Rechnung konnte nicht geschrieben werden (' . $tmpPdf . ')');
                }

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
            'person_id' => (new PersonModel())->findIdFuerName($daten['person']),
            'typ' => $daten['typ'] ?? 'forderung',
            'kategorie' => $daten['kategorie'] ?? 'getraenke',
            'datum' => $daten['datum'],
            'grund' => $daten['grund'],
            'betrag' => $daten['betrag'],
        ];
    }
}
