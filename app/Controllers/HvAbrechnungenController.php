<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\HvAbrechnungModel;
use App\Models\AbrechnungBelegModel;
use App\Models\BelegModel;

/**
 * HvAbrechnungenController - Heimverein Abrechnungen
 *
 * Verwendet gemeinsame Views mit AH²-Controller über $typ Variable
 */
class HvAbrechnungenController extends BaseController
{
    protected $hvAbrechnungModel;
    protected $abrechnungBelegModel;
    protected $belegModel;

    public function __construct()
    {
        $this->hvAbrechnungModel = new HvAbrechnungModel();
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
        $this->belegModel = new BelegModel();
    }

    /**
     * Übersicht aller HV-Abrechnungen
     */
    public function index()
    {
        $abrechnungen = $this->hvAbrechnungModel->getAbrechnungenMitBeleganzahl();

        $data = [
            'title' => 'Heimverein Abrechnungen',
            'typ' => 'hv',  // Wichtig für gemeinsame Views
            'abrechnungen' => $abrechnungen,
            'stats' => $this->hvAbrechnungModel->getDashboardStats()
        ];

        return view('abrechnungen/index', $data);
    }

    /**
     * Neue HV-Abrechnung erstellen
     */
    public function create()
    {
        $data = [
            'title' => 'Neue HV Abrechnung',
            'typ' => 'hv',
            'aktueller_monat' => date('Y-m'),
            'verfuegbare_belege_count' => count($this->hvAbrechnungModel->getVerfuegbareBelege()),
            'stats' => $this->hvAbrechnungModel->getDashboardStats()
        ];

        return view('abrechnungen/create', $data);
    }

    /**
     * HV-Abrechnung speichern
     */
    public function store()
    {
        $rules = [
            'abrechnungsmonat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            'titel' => 'required|min_length[3]',
            'begruendung' => 'max_length[1000]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'abrechnungsmonat' => $this->request->getPost('abrechnungsmonat'),
            'titel' => $this->request->getPost('titel'),
            'begruendung' => $this->request->getPost('begruendung'),
            'notizen' => $this->request->getPost('notizen')
        ];

        $abrechnungId = $this->hvAbrechnungModel->erstelleAbrechnung($data);

        if ($abrechnungId) {
            return redirect()->to("/abrechnungen/hv/belege/{$abrechnungId}")
                ->with('success', 'HV Abrechnung wurde erstellt! Wählen Sie nun die Belege aus.');
        } else {
            return redirect()->back()->withInput()->with('errors', $this->hvAbrechnungModel->errors());
        }
    }

    /**
     * Belege für HV-Abrechnung auswählen
     */
    public function selectBelege($id)
    {
        $abrechnung = $this->hvAbrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        $verfuegbareBelege = $this->abrechnungBelegModel->getVerfuegbareBelege('hv', $id);
        $zugeordneteBelege = $this->abrechnungBelegModel->getZugeordneteBelege('hv', $id);

        $data = [
            'title' => 'Belege auswählen: ' . $abrechnung['titel'],
            'typ' => 'hv',
            'abrechnung' => $abrechnung,
            'verfuegbare_belege' => $verfuegbareBelege,
            'zugeordnete_belege' => $zugeordneteBelege
        ];

        return view('abrechnungen/select_belege', $data);
    }

    /**
     * Beleg zur HV-Abrechnung hinzufügen (AJAX)
     */
    public function addBeleg($abrechnungId)
    {
        $belegId = $this->request->getPost('beleg_id');

        if (!$belegId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Keine Beleg-ID erhalten']);
        }

        $result = $this->abrechnungBelegModel->fuegeZuordnungHinzu($belegId, 'hv', $abrechnungId);

        if ($result) {
            $this->hvAbrechnungModel->berechneGesamtsumme($abrechnungId);
            $abrechnung = $this->hvAbrechnungModel->find($abrechnungId);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Beleg wurde hinzugefügt',
                'neue_gesamtsumme' => number_format($abrechnung['gesamtsumme'], 2, ',', '.') . ' €'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => implode(', ', $this->abrechnungBelegModel->errors())
            ]);
        }
    }

    /**
     * Beleg aus HV-Abrechnung entfernen (AJAX)
     */
    public function removeBeleg($abrechnungId)
    {
        $belegId = $this->request->getPost('beleg_id');

        if ($this->abrechnungBelegModel->entferneZuordnung($belegId, 'hv', $abrechnungId)) {
            $this->hvAbrechnungModel->berechneGesamtsumme($abrechnungId);
            $abrechnung = $this->hvAbrechnungModel->find($abrechnungId);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Beleg wurde entfernt',
                'neue_gesamtsumme' => number_format($abrechnung['gesamtsumme'], 2, ',', '.') . ' €'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Fehler beim Entfernen des Belegs'
            ]);
        }
    }

    /**
     * HV-Abrechnung bearbeiten (Begründung ändern)
     */
    public function edit($id)
    {
        $abrechnung = $this->hvAbrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        $data = [
            'title' => 'HV Abrechnung bearbeiten',
            'typ' => 'hv',
            'abrechnung' => $abrechnung
        ];

        return view('abrechnungen/edit', $data);
    }

    /**
     * HV-Abrechnung aktualisieren
     */
    public function update($id)
    {
        $rules = [
            'titel' => 'required|min_length[3]',
            'begruendung' => 'max_length[1000]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $updateData = [
            'titel' => $this->request->getPost('titel'),
            'begruendung' => $this->request->getPost('begruendung'),
            'notizen' => $this->request->getPost('notizen')
        ];

        if ($this->hvAbrechnungModel->update($id, $updateData)) {
            return redirect()->to("/abrechnungen/hv/preview/{$id}")
                ->with('success', 'HV Abrechnung wurde aktualisiert!');
        } else {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Aktualisieren.');
        }
    }

    /**
     * HV-Abrechnung Vorschau
     */
    public function preview($id)
    {
        $abrechnung = $this->hvAbrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        $belege = $this->hvAbrechnungModel->getBelege($id);

        $data = [
            'title' => 'Vorschau: ' . $abrechnung['titel'],
            'typ' => 'hv',
            'abrechnung' => $abrechnung,
            'belege' => $belege
        ];

        return view('abrechnungen/preview', $data);
    }

    /**
     * Status ändern
     */
    public function changeStatus($id)
    {
        $neuerStatus = $this->request->getPost('status');

        if ($this->hvAbrechnungModel->aendereStatus($id, $neuerStatus)) {
            $statusText = $this->hvAbrechnungModel->formatiereStatus($neuerStatus);
            return redirect()->back()->with('success', "Status wurde zu '{$statusText}' geändert!");
        } else {
            return redirect()->back()->with('error', 'Fehler beim Ändern des Status.');
        }
    }

    /**
     * Excel-Export mit HV-Begründungen (echtes Excel-Format)
     */
    public function exportExcel($id)
    {
        // Prüfen ob PhpSpreadsheet verfügbar ist
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return redirect()->back()->with('error', 'PhpSpreadsheet ist nicht installiert.');
        }

        $abrechnung = $this->hvAbrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->back()->with('error', 'Abrechnung nicht gefunden.');
        }

        $belege = $this->hvAbrechnungModel->getBelege($id);

        try {
            // Excel mit PhpSpreadsheet erstellen
            require_once APPPATH . 'Helpers/ExcelHelper.php';
            $spreadsheet = \App\Helpers\ExcelHelper::erstelleHvAbrechnung($abrechnung, $belege);

            $filename = 'HV_Abrechnung_' . $abrechnung['abrechnungsmonat'] . '.xlsx';

            \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);

        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * HV-Abrechnung löschen
     */
    public function delete($id)
    {
        $abrechnung = $this->hvAbrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->to('/abrechnungen/hv')->with('error', 'Abrechnung nicht gefunden.');
        }

        if ($abrechnung['status'] !== 'entwurf') {
            return redirect()->back()->with('error', 'Nur Entwürfe können gelöscht werden.');
        }

        $this->abrechnungBelegModel->loescheAlleZuordnungen('hv', $id);

        if ($this->hvAbrechnungModel->delete($id)) {
            return redirect()->to('/abrechnungen/hv')
                ->with('success', 'HV Abrechnung wurde gelöscht!');
        } else {
            return redirect()->back()->with('error', 'Fehler beim Löschen der Abrechnung.');
        }
    }
}