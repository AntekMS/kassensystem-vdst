<?php

namespace App\Controllers;

use App\Models\BuchungModel;
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

        $data = [
            'title' => 'Schuldenliste',
            'personen' => $this->schuldModel->getPersonenUebersicht(),
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

        foreach ($eintraege as $eintrag) {
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

        if ($this->schuldModel->delete($id)) {
            return redirect()->to('/schulden/person?name=' . urlencode($eintrag['person']))
                ->with('success', 'Eintrag wurde erfolgreich gelöscht!');
        }

        return redirect()->to('/schulden')->with('error', 'Fehler beim Löschen des Eintrags.');
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

    // ==================== PRIVATE HELPER METHODS ====================

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
