<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BuchungModel;
use App\Models\BelegModel;

/**
 * BuchungenController - Kassenbuch-Verwaltung
 *
 * Wie ein digitales Kassenbuch:
 * - Alle Buchungen verwalten
 * - Optional mit Belegen verknüpfen
 * - Excel-Export für Kassenbuch
 */
class BuchungenController extends BaseController
{
    protected $buchungModel;
    protected $belegModel;

    public function __construct()
    {
        $this->buchungModel = new BuchungModel();
        $this->belegModel = new BelegModel();
    }

    /**
     * Kassenbuch-Übersicht
     */
    public function index()
    {
        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);

        $data = [
            'title' => 'Kassenbuch',
            'buchungen' => $buchungen,
            'filter' => $filter,
            'kontostaende' => $this->buchungModel->berechneKontostaende(),
            'stats' => $this->buchungModel->getDashboardStats()
        ];

        return view('buchungen/index', $data);
    }

    /**
     * Neue Buchung erstellen
     */
    public function create()
    {
        $data = [
            'title' => 'Neue Buchung',
            'verfuegbare_belege' => $this->buchungModel->getVerfuegbareBelegeFuerBuchung(),
            'konten' => [
                'aktivenkasse' => 'Aktivenkasse',
                'getraenkekasse' => 'Getränkekasse',
                'barkasse' => 'Barkasse'
            ]
        ];

        return view('buchungen/create', $data);
    }

    /**
     * Buchung speichern
     */
    public function store()
    {
        $rules = [
            'buchungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]',
            'betrag' => 'required|decimal|greater_than[0]',
            'konto_typ' => 'required|in_list[aktivenkasse,getraenkekasse,barkasse]',
            'buchungsart' => 'required|in_list[ausgabe,einnahme]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'beleg_id' => $this->request->getPost('beleg_id') ?: null,
            'buchungsdatum' => $this->request->getPost('buchungsdatum'),
            'beschreibung' => $this->request->getPost('beschreibung'),
            'betrag' => $this->request->getPost('betrag'),
            'konto_typ' => $this->request->getPost('konto_typ'),
            'buchungsart' => $this->request->getPost('buchungsart'),
            'notizen' => $this->request->getPost('notizen')
        ];

        if ($this->buchungModel->erstelleBuchung($data)) {
            return redirect()->to('/buchungen')->with('success', 'Buchung wurde erfolgreich erstellt!');
        } else {
            return redirect()->back()->withInput()->with('errors', $this->buchungModel->errors());
        }
    }

    /**
     * Buchung bearbeiten
     */
    public function edit($id)
    {
        $buchung = $this->buchungModel->find($id);

        if (!$buchung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Buchung nicht gefunden');
        }

        $data = [
            'title' => 'Buchung bearbeiten',
            'buchung' => $buchung,
            'verfuegbare_belege' => $this->buchungModel->getVerfuegbareBelegeFuerBuchung(),
            'konten' => [
                'aktivenkasse' => 'Aktivenkasse',
                'getraenkekasse' => 'Getränkekasse',
                'barkasse' => 'Barkasse'
            ]
        ];

        return view('buchungen/edit', $data);
    }

    /**
     * Buchung aktualisieren
     */
    public function update($id)
    {
        $buchung = $this->buchungModel->find($id);

        if (!$buchung) {
            return redirect()->to('/buchungen')->with('error', 'Buchung nicht gefunden.');
        }

        $rules = [
            'buchungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]',
            'betrag' => 'required|decimal|greater_than[0]',
            'konto_typ' => 'required|in_list[aktivenkasse,getraenkekasse,barkasse]',
            'buchungsart' => 'required|in_list[ausgabe,einnahme]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $updateData = [
            'beleg_id' => $this->request->getPost('beleg_id') ?: null,
            'buchungsdatum' => $this->request->getPost('buchungsdatum'),
            'beschreibung' => $this->request->getPost('beschreibung'),
            'betrag' => $this->request->getPost('betrag'),
            'konto_typ' => $this->request->getPost('konto_typ'),
            'buchungsart' => $this->request->getPost('buchungsart'),
            'notizen' => $this->request->getPost('notizen')
        ];

        if ($this->buchungModel->update($id, $updateData)) {
            return redirect()->to('/buchungen')->with('success', 'Buchung wurde erfolgreich aktualisiert!');
        } else {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Aktualisieren der Buchung.');
        }
    }

    /**
     * Buchung löschen
     */
    public function delete($id)
    {
        $buchung = $this->buchungModel->find($id);

        if (!$buchung) {
            return redirect()->to('/buchungen')->with('error', 'Buchung nicht gefunden.');
        }

        if ($this->buchungModel->delete($id)) {
            return redirect()->to('/buchungen')->with('success', 'Buchung wurde erfolgreich gelöscht!');
        } else {
            return redirect()->to('/buchungen')->with('error', 'Fehler beim Löschen der Buchung.');
        }
    }

    /**
     * Excel-Export des Kassenbuchs (echtes Excel wie deine Vorlage)
     */
    public function exportExcel()
    {
        // Prüfen ob PhpSpreadsheet verfügbar ist
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return redirect()->back()->with('error', 'PhpSpreadsheet ist nicht installiert. Bitte führe "composer require phpoffice/phpspreadsheet" aus.');
        }

        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);
        $kontostaende = $this->buchungModel->berechneKontostaende();

        try {
            // Excel mit PhpSpreadsheet erstellen
            require_once APPPATH . 'Helpers/ExcelHelper.php';
            $spreadsheet = \App\Helpers\ExcelHelper::erstelleKassenbuch($buchungen, $kontostaende, $filter);

            $filename = 'Kassenbuch_' . date('Y-m-d') . '.xlsx';

            \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);

        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Beleg-Details für Auswahl
     */
    public function getBelegDetails($belegId)
    {
        $beleg = $this->belegModel->find($belegId);

        if (!$beleg) {
            return $this->response->setJSON(['error' => 'Beleg nicht gefunden']);
        }

        return $this->response->setJSON([
            'belegnummer' => $beleg['belegnummer'],
            'beschreibung' => $beleg['beschreibung'],
            'betrag' => $beleg['betrag'],
            'lieferant' => $beleg['lieferant']
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * Filter aus Request extrahieren
     */
    private function getFilterFromRequest()
    {
        return [
            'datum_von' => $this->request->getGet('datum_von'),
            'datum_bis' => $this->request->getGet('datum_bis'),
            'konto_typ' => $this->request->getGet('konto_typ'),
            'buchungsart' => $this->request->getGet('buchungsart'),
            'suche' => $this->request->getGet('suche')
        ];
    }
}