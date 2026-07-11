<?php

namespace App\Controllers;

use App\Models\AbrechnungBelegModel;
use App\Models\SchuldModel;

/**
 * AbstractAbrechnungenController - Gemeinsame Logik für AH²- und HV-Abrechnungen
 *
 * Die beiden Abrechnungstypen unterscheiden sich nur durch Typ-Kürzel,
 * Modell und das HV-spezifische Begründungsfeld. Alles andere (Belege
 * zuordnen, Status, Export, Löschen) läuft hier über einen Codepfad.
 * Die Views (app/Views/abrechnungen/) sind bereits über $typ geteilt.
 */
abstract class AbstractAbrechnungenController extends BaseController
{
    /** @var string 'ah' oder 'hv' */
    protected string $typ;

    /** @var string Anzeigename, z.B. 'AH²' oder 'HV' */
    protected string $typName;

    /** @var \App\Models\AhAbrechnungModel|\App\Models\HvAbrechnungModel */
    protected $abrechnungModel;

    protected AbrechnungBelegModel $abrechnungBelegModel;

    public function __construct()
    {
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
    }

    /**
     * Übersicht aller Abrechnungen
     */
    public function index()
    {
        $data = [
            'title' => "{$this->typName} Abrechnungen",
            'typ' => $this->typ,
            'abrechnungen' => $this->abrechnungModel->getAbrechnungenMitBeleganzahl(),
            'stats' => $this->abrechnungModel->getDashboardStats(),
        ];

        return view('abrechnungen/index', $data);
    }

    /**
     * Formular für neue Abrechnung
     */
    public function create()
    {
        $data = [
            'title' => "Neue {$this->typName} Abrechnung",
            'typ' => $this->typ,
            'aktueller_monat' => date('Y-m'),
            'verfuegbare_belege_count' => count($this->abrechnungModel->getVerfuegbareBelege()),
            'stats' => $this->abrechnungModel->getDashboardStats(),
        ];

        return view('abrechnungen/create', $data);
    }

    /**
     * Abrechnung speichern
     */
    public function store()
    {
        $rules = [
            'abrechnungsmonat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
            'titel' => 'required|min_length[3]',
        ];

        if ($this->typ === 'hv') {
            $rules['begruendung'] = 'permit_empty|max_length[1000]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'abrechnungsmonat' => $this->request->getPost('abrechnungsmonat'),
            'titel' => $this->request->getPost('titel'),
            'notizen' => $this->request->getPost('notizen'),
        ];

        if ($this->typ === 'hv') {
            $data['begruendung'] = $this->request->getPost('begruendung');
        }

        $abrechnungId = $this->abrechnungModel->erstelleAbrechnung($data);

        if ($abrechnungId) {
            return redirect()->to("/abrechnungen/{$this->typ}/belege/{$abrechnungId}")
                ->with('success', "{$this->typName} Abrechnung wurde erstellt! Wählen Sie nun die Belege aus.");
        }

        return redirect()->back()->withInput()->with('errors', $this->abrechnungModel->errors());
    }

    /**
     * Belege für Abrechnung auswählen
     */
    public function selectBelege($id)
    {
        $abrechnung = $this->findeAbrechnungOder404($id);

        $data = [
            'title' => 'Belege auswählen: ' . $abrechnung['titel'],
            'typ' => $this->typ,
            'abrechnung' => $abrechnung,
            'verfuegbare_belege' => $this->abrechnungBelegModel->getVerfuegbareBelege($this->typ, $id),
            'zugeordnete_belege' => $this->abrechnungBelegModel->getZugeordneteBelege($this->typ, $id),
        ];

        return view('abrechnungen/select_belege', $data);
    }

    /**
     * Beleg zur Abrechnung hinzufügen (AJAX)
     */
    public function addBeleg($abrechnungId)
    {
        $belegId = $this->request->getPost('beleg_id');

        if (!$belegId || !is_numeric($belegId)) {
            return $this->jsonAntwort(false, 'Keine gültige Beleg-ID erhalten');
        }

        $abrechnung = $this->abrechnungModel->find($abrechnungId);

        if (!$abrechnung) {
            return $this->jsonAntwort(false, 'Abrechnung nicht gefunden');
        }

        if (in_array($abrechnung['status'], ['eingereicht', 'bezahlt'], true)) {
            return $this->jsonAntwort(false, 'Eingereichte oder bezahlte Abrechnungen können nicht mehr bearbeitet werden');
        }

        if (!$this->abrechnungBelegModel->fuegeZuordnungHinzu($belegId, $this->typ, $abrechnungId)) {
            return $this->jsonAntwort(false, implode(', ', $this->abrechnungBelegModel->errors()));
        }

        $this->abrechnungModel->berechneGesamtsumme($abrechnungId);
        $abrechnung = $this->abrechnungModel->find($abrechnungId);

        return $this->jsonAntwort(true, 'Beleg wurde hinzugefügt', $abrechnung['gesamtsumme']);
    }

    /**
     * Beleg aus Abrechnung entfernen (AJAX)
     */
    public function removeBeleg($abrechnungId)
    {
        $belegId = $this->request->getPost('beleg_id');

        if (!$belegId || !is_numeric($belegId)) {
            return $this->jsonAntwort(false, 'Keine gültige Beleg-ID erhalten');
        }

        $abrechnung = $this->abrechnungModel->find($abrechnungId);

        if (!$abrechnung) {
            return $this->jsonAntwort(false, 'Abrechnung nicht gefunden');
        }

        if ($abrechnung['status'] === 'bezahlt') {
            return $this->jsonAntwort(false, 'Bezahlte Abrechnungen können nicht mehr bearbeitet werden');
        }

        if (!$this->abrechnungBelegModel->entferneZuordnung($belegId, $this->typ, $abrechnungId)) {
            return $this->jsonAntwort(false, 'Beleg konnte nicht entfernt werden');
        }

        $this->abrechnungModel->berechneGesamtsumme($abrechnungId);
        $abrechnung = $this->abrechnungModel->find($abrechnungId);

        return $this->jsonAntwort(true, 'Beleg wurde erfolgreich entfernt', $abrechnung['gesamtsumme']);
    }

    /**
     * Abrechnung bearbeiten (Titel, Notizen, bei HV auch Begründung)
     */
    public function edit($id)
    {
        $abrechnung = $this->findeAbrechnungOder404($id);

        $data = [
            'title' => "{$this->typName} Abrechnung bearbeiten",
            'typ' => $this->typ,
            'abrechnung' => $abrechnung,
        ];

        return view('abrechnungen/edit', $data);
    }

    /**
     * Abrechnung aktualisieren
     */
    public function update($id)
    {
        $this->findeAbrechnungOder404($id);

        $rules = [
            'titel' => 'required|min_length[3]',
        ];

        if ($this->typ === 'hv') {
            $rules['begruendung'] = 'permit_empty|max_length[1000]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $updateData = [
            'titel' => $this->request->getPost('titel'),
            'notizen' => $this->request->getPost('notizen'),
        ];

        if ($this->typ === 'hv') {
            $updateData['begruendung'] = $this->request->getPost('begruendung');
        }

        if ($this->abrechnungModel->update($id, $updateData)) {
            return redirect()->to("/abrechnungen/{$this->typ}/preview/{$id}")
                ->with('success', "{$this->typName} Abrechnung wurde aktualisiert!");
        }

        return redirect()->back()->withInput()->with('error', 'Fehler beim Aktualisieren.');
    }

    /**
     * Abrechnung-Vorschau vor Export
     */
    public function preview($id)
    {
        $abrechnung = $this->findeAbrechnungOder404($id);

        $data = [
            'title' => 'Vorschau: ' . $abrechnung['titel'],
            'typ' => $this->typ,
            'abrechnung' => $abrechnung,
            'belege' => $this->abrechnungModel->getBelege($id),
        ];

        return view('abrechnungen/preview', $data);
    }

    /**
     * Status der Abrechnung ändern
     */
    public function changeStatus($id)
    {
        $neuerStatus = $this->request->getPost('status');

        if ($this->abrechnungModel->aendereStatus($id, $neuerStatus)) {
            // Schuldenliste nachziehen: eingereicht → Forderung gegen AH²-Bund/Heimverein,
            // bezahlt → zusätzlich Ausgleich; Zurückstufen entfernt die Einträge wieder
            (new SchuldModel())->syncAbrechnungForderung($this->typ, $this->abrechnungModel->find($id));

            $statusText = abrechnung_status_label($neuerStatus);

            return redirect()->back()->with('success', "Status wurde zu '{$statusText}' geändert!");
        }

        return redirect()->back()->with('error', 'Fehler beim Ändern des Status.');
    }

    /**
     * Excel-Export der Abrechnung
     */
    public function exportExcel($id)
    {
        $abrechnung = $this->abrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->back()->with('error', 'Abrechnung nicht gefunden.');
        }

        $belege = $this->abrechnungModel->getBelege($id);

        try {
            $spreadsheet = $this->typ === 'ah'
                ? \App\Helpers\ExcelHelper::erstelleAhAbrechnung($abrechnung, $belege)
                : \App\Helpers\ExcelHelper::erstelleHvAbrechnung($abrechnung, $belege);

            $filename = strtoupper($this->typ) . '_Abrechnung_' . $abrechnung['abrechnungsmonat'] . '.xlsx';

            return \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);
        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * ZIP-Download aller Belege einer Abrechnung (inkl. Excel)
     */
    public function downloadBelegeZip($id)
    {
        $abrechnung = $this->abrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->back()->with('error', 'Abrechnung nicht gefunden.');
        }

        $belege = $this->abrechnungModel->getBelege($id);

        if (empty($belege)) {
            return redirect()->back()->with('error', 'Keine Belege in dieser Abrechnung gefunden.');
        }

        try {
            \App\Helpers\ZipHelper::cleanupTempZips();
            $zipPath = \App\Helpers\ZipHelper::erstelleBelegeZip($abrechnung, $belege, $this->typ);

            if (!$zipPath || !file_exists($zipPath)) {
                throw new \Exception('ZIP-Datei konnte nicht erstellt werden.');
            }

            $filename = strtoupper($this->typ) . '_Belege_' . $abrechnung['abrechnungsmonat'] . '_'
                . preg_replace('/[^a-zA-Z0-9]/', '_', $abrechnung['titel']) . '.zip';

            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setContentType('application/zip');
        } catch (\Exception $e) {
            log_message('error', 'ZIP-Download Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim ZIP-Download: ' . $e->getMessage());
        }
    }

    /**
     * Abrechnung löschen (nur Entwürfe und ausstehende Abrechnungen)
     */
    public function delete($id)
    {
        $abrechnung = $this->abrechnungModel->find($id);

        if (!$abrechnung) {
            return redirect()->to("/abrechnungen/{$this->typ}")->with('error', 'Abrechnung nicht gefunden.');
        }

        if (!in_array($abrechnung['status'], ['entwurf', 'ausstehend'], true)) {
            $statusText = abrechnung_status_label($abrechnung['status']);

            return redirect()->back()->with('error',
                "Abrechnung mit Status '{$statusText}' kann nicht gelöscht werden. Nur Entwürfe und ausstehende Abrechnungen sind löschbar.");
        }

        try {
            // Zuordnungen entfernen (die Belege selbst bleiben bestehen)
            $this->abrechnungBelegModel->loescheAlleZuordnungen($this->typ, $id);

            if ($this->abrechnungModel->delete($id)) {
                return redirect()->to("/abrechnungen/{$this->typ}")
                    ->with('success', "{$this->typName} Abrechnung '{$abrechnung['titel']}' wurde erfolgreich gelöscht!");
            }

            return redirect()->back()->with('error', 'Fehler beim Löschen der Abrechnung.');
        } catch (\Exception $e) {
            log_message('error', 'Fehler beim Löschen der Abrechnung: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Ein unerwarteter Fehler ist beim Löschen aufgetreten.');
        }
    }

    /**
     * Holt eine Abrechnung oder wirft 404
     */
    protected function findeAbrechnungOder404($id): array
    {
        $abrechnung = $this->abrechnungModel->find($id);

        if (!$abrechnung) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Abrechnung nicht gefunden');
        }

        return $abrechnung;
    }

    /**
     * Einheitliche AJAX-Antwort. Enthält immer den neuen CSRF-Hash,
     * da jeder POST den Token rotiert (Security::$regenerate = true).
     */
    protected function jsonAntwort(bool $success, string $message, $gesamtsumme = null)
    {
        $antwort = [
            'success' => $success,
            'message' => $message,
            'csrf_hash' => csrf_hash(),
        ];

        if ($gesamtsumme !== null) {
            $antwort['neue_gesamtsumme'] = formatiere_betrag($gesamtsumme);
        }

        return $this->response->setJSON($antwort);
    }
}
