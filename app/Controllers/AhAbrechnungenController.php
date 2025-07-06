<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AhAbrechnungModel;
use App\Models\AbrechnungBelegModel;
use App\Models\BelegModel;

/**
 * AhAbrechnungenController - AH² Abrechnungen
 *
 * Verwendet gemeinsame Views mit HV-Controller über $typ Variable
 */
class AhAbrechnungenController extends BaseController
{
    protected $ahAbrechnungModel;
    protected $abrechnungBelegModel;
    protected $belegModel;

    public function __construct()
    {
        $this->ahAbrechnungModel = new AhAbrechnungModel();
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
        $this->belegModel = new BelegModel();
    }

    /**
     * Übersicht aller AH²-Abrechnungen
     */
    public function index()
    {
        $abrechnungen = $this->ahAbrechnungModel->getAbrechnungenMitBeleganzahl();

        $data = [
            'title' => 'AH² Abrechnungen',
            'typ' => 'ah',  // Wichtig für gemeinsame Views
            'abrechnungen' => $abrechnungen,
            'stats' => $this->ahAbrechnungModel->getDashboardStats()
        ];

        return view('abrechnungen/index', $data);
    }

    /**
     * Neue AH²-Abrechnung erstellen
     */
    public function create()
    {
        $data = [
            'title' => 'Neue AH² Abrechnung',
            'typ' => 'ah',
            'aktueller_monat' => date('Y-m'),
            'verfuegbare_belege_count' => count($this->ahAbrechnungModel->getVerfuegbareBelege()),
            'stats' => $this->ahAbrechnungModel->getDashboardStats()
        ];

        return view('abrechnungen/create', $data);
    }

    /**
     * AH²-Abrechnung speichern
     */
    public function store()
    {
        $rules = [
            'abrechnungsmonat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            'titel' => 'required|min_length[3]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'abrechnungsmonat' => $this->request->getPost('abrechnungsmonat'),
            'titel' => $this->request->getPost('titel'),
            'notizen' => $this->request->getPost('notizen')
        ];

        $abrechnungId = $this->ahAbrechnungModel->erstelleAbrechnung($data);

        if ($abrechnungId) {
            return redirect()->to("/abrechnungen/ah/belege/{$abrechnungId}")
                ->with('success', 'AH² Abrechnung wurde erstellt! Wählen Sie nun die Belege aus.');
        } else {
            return redirect()->back()->withInput()->with('errors', $this->ahAbrechnungModel->errors());
        }
    }

    /**
     * Belege für Abrechnung auswählen - DAS HERZSTÜCK
     */
    public function selectBelege($id)
    {
        $abrechnung = $this->ahAbrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        $verfuegbareBelege = $this->abrechnungBelegModel->getVerfuegbareBelege('ah', $id);
        $zugeordneteBelege = $this->abrechnungBelegModel->getZugeordneteBelege('ah', $id);

        $data = [
            'title' => 'Belege auswählen: ' . $abrechnung['titel'],
            'typ' => 'ah',
            'abrechnung' => $abrechnung,
            'verfuegbare_belege' => $verfuegbareBelege,
            'zugeordnete_belege' => $zugeordneteBelege
        ];

        return view('abrechnungen/select_belege', $data);
    }

    /**
     * Beleg zur Abrechnung hinzufügen (AJAX)
     */
    public function addBeleg($abrechnungId)
    {
        $belegId = $this->request->getPost('beleg_id');

        if (!$belegId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Keine Beleg-ID erhalten']);
        }

        $result = $this->abrechnungBelegModel->fuegeZuordnungHinzu($belegId, 'ah', $abrechnungId);

        if ($result) {
            // Neue Gesamtsumme berechnen
            $this->ahAbrechnungModel->berechneGesamtsumme($abrechnungId);
            $abrechnung = $this->ahAbrechnungModel->find($abrechnungId);

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
     * Beleg aus Abrechnung entfernen (AJAX)
     */
    public function removeBeleg($abrechnungId)
    {
        // Validierung der Request-Methode
        if (!$this->request->isAJAX() || $this->request->getMethod() !== 'post') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Ungültige Anfrage'
            ]);
        }

        $belegId = $this->request->getPost('beleg_id');

        if (!$belegId || !is_numeric($belegId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Keine gültige Beleg-ID erhalten'
            ]);
        }

        // Prüfe ob Abrechnung existiert und im richtigen Status ist
        $abrechnung = $this->ahAbrechnungModel->find($abrechnungId);
        if (!$abrechnung) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Abrechnung nicht gefunden'
            ]);
        }

        if ($abrechnung['status'] === 'bezahlt') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Bezahlte Abrechnungen können nicht mehr bearbeitet werden'
            ]);
        }

        try {
            // Beleg aus Abrechnung entfernen
            $entfernt = $this->abrechnungBelegModel->entferneZuordnung($belegId, 'ah', $abrechnungId);

            if ($entfernt) {
                // Gesamtsumme neu berechnen
                $this->ahAbrechnungModel->berechneGesamtsumme($abrechnungId);
                $abrechnung = $this->ahAbrechnungModel->find($abrechnungId);

                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Beleg wurde erfolgreich entfernt',
                    'neue_gesamtsumme' => number_format($abrechnung['gesamtsumme'], 2, ',', '.') . ' €'
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Beleg konnte nicht entfernt werden. Möglicherweise ist er nicht in dieser Abrechnung.'
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', 'Fehler beim Entfernen des Belegs: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten'
            ]);
        }
    }

    /**
     * Abrechnung-Vorschau vor Export
     */
    public function preview($id)
    {
        $abrechnung = $this->ahAbrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        $belege = $this->ahAbrechnungModel->getBelege($id);

        $data = [
            'title' => 'Vorschau: ' . $abrechnung['titel'],
            'typ' => 'ah',
            'abrechnung' => $abrechnung,
            'belege' => $belege
        ];

        return view('abrechnungen/preview', $data);
    }

    /**
     * Status der Abrechnung ändern
     */
    public function changeStatus($id)
    {
        $neuerStatus = $this->request->getPost('status');

        if ($this->ahAbrechnungModel->aendereStatus($id, $neuerStatus)) {
            $statusText = $this->ahAbrechnungModel->formatiereStatus($neuerStatus);
            return redirect()->back()->with('success', "Status wurde zu '{$statusText}' geändert!");
        } else {
            return redirect()->back()->with('error', 'Fehler beim Ändern des Status.');
        }
    }

    /**
     * Excel-Export der Abrechnung (echtes Excel-Format)
     */
    public function exportExcel($id)
    {
        // Prüfen ob PhpSpreadsheet verfügbar ist
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return redirect()->back()->with('error', 'PhpSpreadsheet ist nicht installiert.');
        }

        $abrechnung = $this->ahAbrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->back()->with('error', 'Abrechnung nicht gefunden.');
        }

        $belege = $this->ahAbrechnungModel->getBelege($id);

        try {
            // Excel mit PhpSpreadsheet erstellen
            require_once APPPATH . 'Helpers/ExcelHelper.php';
            $spreadsheet = \App\Helpers\ExcelHelper::erstelleAhAbrechnung($abrechnung, $belege);

            $filename = 'AH_Abrechnung_' . $abrechnung['abrechnungsmonat'] . '.xlsx';

            \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);

        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * ZIP-Download aller Belege einer AH²-Abrechnung
     * Wie ein intelligenter Kopierer, der alle Akten zusammenpackt
     */
    public function downloadBelegeZip($id)
    {
        $abrechnung = $this->ahAbrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->back()->with('error', 'Abrechnung nicht gefunden.');
        }

        $belege = $this->ahAbrechnungModel->getBelege($id);

        if (empty($belege)) {
            return redirect()->back()->with('error', 'Keine Belege in dieser Abrechnung gefunden.');
        }

        try {
            // ZIP-Helper verwenden
            require_once APPPATH . 'Helpers/ZipHelper.php';
            $zipPath = \App\Helpers\ZipHelper::erstelleBelegeZip($abrechnung, $belege, 'ah');

            if (!$zipPath || !file_exists($zipPath)) {
                throw new \Exception('ZIP-Datei konnte nicht erstellt werden.');
            }

            $filename = 'AH_Belege_' . $abrechnung['abrechnungsmonat'] . '_' .
                preg_replace('/[^a-zA-Z0-9]/', '_', $abrechnung['titel']) . '.zip';

            // ZIP-Download mit automatischer Löschung
            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setContentType('application/zip');

        } catch (\Exception $e) {
            log_message('error', 'ZIP-Download Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim ZIP-Download: ' . $e->getMessage());
        }
    }

    /**
     * Abrechnung löschen
     */
    public function delete($id)
    {
        $abrechnung = $this->ahAbrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->to('/abrechnungen/ah')->with('error', 'Abrechnung nicht gefunden.');
        }

        // Erweiterte Lösch-Bedingungen
        $kannGeloeschtWerden = in_array($abrechnung['status'], ['entwurf', 'ausstehend']);

        if (!$kannGeloeschtWerden) {
            $statusText = $this->ahAbrechnungModel->formatiereStatus($abrechnung['status']);
            return redirect()->back()->with('error',
                "Abrechnung mit Status '{$statusText}' kann nicht gelöscht werden. Nur Entwürfe und ausstehende Abrechnungen sind löschbar.");
        }

        try {
            // Schritt 1: Alle Beleg-Zuordnungen entfernen (Belege bleiben bestehen)
            $this->abrechnungBelegModel->loescheAlleZuordnungen('ah', $id);

            // Schritt 2: Abrechnung löschen
            if ($this->ahAbrechnungModel->delete($id)) {
                return redirect()->to('/abrechnungen/ah')
                    ->with('success', "AH² Abrechnung '{$abrechnung['titel']}' wurde erfolgreich gelöscht!");
            } else {
                return redirect()->back()->with('error', 'Fehler beim Löschen der Abrechnung aus der Datenbank.');
            }

        } catch (\Exception $e) {
            log_message('error', 'Fehler beim Löschen der Abrechnung: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Ein unerwarteter Fehler ist beim Löschen aufgetreten.');
        }
    }
}