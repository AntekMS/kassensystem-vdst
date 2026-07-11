<?php

namespace App\Controllers;

use App\Libraries\BelegUpload;
use App\Models\BelegModel;
use App\Models\BuchungModel;
use App\Models\SchuldModel;

/**
 * BuchungenController - Kassenbuch-Verwaltung
 *
 * - Ein-/Ausgaben auf drei Konten buchen
 * - Optionaler Beleg-Upload direkt bei der Buchung
 * - Export als Kassenbuch-Excel oder Komplett-ZIP
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

        $data = [
            'title' => 'Kassenbuch',
            'buchungen' => $this->buchungModel->getBuchungenMitBelegen($filter),
            'filter' => $filter,
            'kontostaende' => $this->buchungModel->berechneKontostaende(),
            'stats' => $this->buchungModel->getDashboardStats(),
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
            'konten' => konto_optionen(),
            'personen_namen' => (new SchuldModel())->getPersonenNamen(),
            'schuld_kategorien' => schuld_kategorie_optionen(),
        ];

        return view('buchungen/create', $data);
    }

    /**
     * Buchung speichern (optional mit direktem Beleg-Upload)
     */
    public function store()
    {
        $daten = $this->request->getPost();
        $daten['betrag'] = normalisiere_betrag($daten['betrag'] ?? null);
        $daten['schuld_person'] = trim(preg_replace('/\s+/', ' ', $daten['schuld_person'] ?? ''));

        $validation = \Config\Services::validation();
        $validation->setRules([
            'buchungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]',
            'betrag' => 'required|decimal|greater_than[0]',
            'konto_typ' => 'required|in_list[aktivenkasse,getraenkekasse,barkasse]',
            'buchungsart' => 'required|in_list[ausgabe,einnahme]',
            'schuld_person' => 'permit_empty|min_length[2]|max_length[100]',
            'schuld_kategorie' => 'permit_empty|in_list[getraenke,abrechnung,sonstige]',
        ]);

        if (!$validation->run($daten)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $belegOption = $daten['beleg_option'] ?? null;
        $belegId = null;

        if ($belegOption === 'beleg_upload' && $this->request->getFile('beleg_datei')) {
            $file = $this->request->getFile('beleg_datei');

            if ($file->isValid() && !$file->hasMoved()) {
                $uploadValidation = \Config\Services::validation();
                $uploadValidation->setRules([
                    'beleg_datei' => 'uploaded[beleg_datei]|max_size[beleg_datei,10240]|ext_in[beleg_datei,pdf,jpg,jpeg,png]|mime_in[beleg_datei,application/pdf,image/jpeg,image/png,image/pjpeg]',
                    'beleg_rechnungsdatum' => 'required|valid_date',
                ]);

                if (!$uploadValidation->run($daten)) {
                    return redirect()->back()->withInput()->with('errors', $uploadValidation->getErrors());
                }

                try {
                    $upload = new BelegUpload($this->belegModel);
                    $belegId = $upload->speichereBeleg($file, [
                        'rechnungsdatum' => $daten['beleg_rechnungsdatum'],
                        // Beschreibung und Betrag von der Buchung übernehmen
                        'beschreibung' => $daten['beschreibung'],
                        'betrag' => $daten['betrag'],
                        'lieferant' => $daten['beleg_lieferant'] ?: null,
                        'kategorie' => $daten['beleg_kategorie'] ?: 'normal',
                        'notizen' => 'Automatisch erstellt bei Buchung',
                    ]);
                } catch (\Exception $e) {
                    log_message('error', 'Beleg-Upload bei Buchung fehlgeschlagen: ' . $e->getMessage());

                    return redirect()->back()->withInput()->with('error', 'Fehler beim Beleg-Upload: ' . $e->getMessage());
                }
            }
        } elseif ($belegOption === 'beleg_waehlen') {
            $belegId = $daten['beleg_id'] ?: null;
        }

        $buchungsDaten = [
            'beleg_id' => $belegId,
            'buchungsdatum' => $daten['buchungsdatum'],
            'beschreibung' => $daten['beschreibung'],
            'betrag' => $daten['betrag'],
            'konto_typ' => $daten['konto_typ'],
            'buchungsart' => $daten['buchungsart'],
            'notizen' => $daten['notizen'] ?? null,
        ];

        $buchungId = $this->buchungModel->erstelleBuchung($buchungsDaten);

        if ($buchungId) {
            // Schulden-Ausgleich angegeben → Rückzahlungs-Eintrag in der Schuldenliste
            if ($daten['schuld_person'] !== '') {
                (new SchuldModel())->erstelleBuchungsAusgleich(
                    $this->buchungModel->find($buchungId),
                    $daten['schuld_person'],
                    $daten['schuld_kategorie'] ?: 'getraenke'
                );
            }

            return redirect()->to('/buchungen')->with('success', 'Buchung wurde erfolgreich erstellt!');
        }

        return redirect()->back()->withInput()->with('errors', $this->buchungModel->errors());
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
            'konten' => konto_optionen(),
            'schuld_eintrag' => (new SchuldModel())->where('buchung_id', $id)->first(),
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

        $daten = $this->request->getPost();
        $daten['betrag'] = normalisiere_betrag($daten['betrag'] ?? null);

        $validation = \Config\Services::validation();
        $validation->setRules([
            'buchungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]',
            'betrag' => 'required|decimal|greater_than[0]',
            'konto_typ' => 'required|in_list[aktivenkasse,getraenkekasse,barkasse]',
            'buchungsart' => 'required|in_list[ausgabe,einnahme]',
        ]);

        if (!$validation->run($daten)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $updateData = [
            'beleg_id' => $daten['beleg_id'] ?: null,
            'buchungsdatum' => $daten['buchungsdatum'],
            'beschreibung' => $daten['beschreibung'],
            'betrag' => $daten['betrag'],
            'konto_typ' => $daten['konto_typ'],
            'buchungsart' => $daten['buchungsart'],
            'notizen' => $daten['notizen'] ?? null,
        ];

        if ($this->buchungModel->update($id, $updateData)) {
            // Verknüpften Schulden-Ausgleich nachziehen (Betrag/Datum/Richtung)
            (new SchuldModel())->syncBuchungAusgleich($this->buchungModel->find($id));

            return redirect()->to('/buchungen')->with('success', 'Buchung wurde erfolgreich aktualisiert!');
        }

        return redirect()->back()->withInput()->with('error', 'Fehler beim Aktualisieren der Buchung.');
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
        }

        return redirect()->to('/buchungen')->with('error', 'Fehler beim Löschen der Buchung.');
    }

    /**
     * Excel-Export des Kassenbuchs
     */
    public function exportExcel()
    {
        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);
        $kontostaende = $this->buchungModel->berechneKontostaende();

        try {
            $spreadsheet = \App\Helpers\ExcelHelper::erstelleKassenbuch($buchungen, $kontostaende, $filter);
            $filename = $this->generiereExportFilename('Kassenbuch', $filter, 'xlsx');

            return \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);
        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * Exportiert gefilterte Buchungen als ZIP mit Kassenbuch-Excel und allen Beleg-Dateien
     */
    public function exportZip()
    {
        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);

        if (empty($buchungen)) {
            return redirect()->back()->with('error', 'Keine Buchungen zum Exportieren gefunden.');
        }

        try {
            \App\Helpers\ZipHelper::cleanupTempZips();

            $tempDir = WRITEPATH . 'temp/zip/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipPath = $tempDir . 'kassenbuch_komplett_' . uniqid('', true) . '.zip';

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden.');
            }

            // 1. Kassenbuch-Excel erstellen und hinzufügen
            $kontostaende = $this->buchungModel->berechneKontostaende();
            $kassenbuchExcel = \App\Helpers\ExcelHelper::erstelleKassenbuch($buchungen, $kontostaende, $filter);
            $kassenbuchFilename = 'Kassenbuch_' . date('Y-m-d') . '.xlsx';
            $tempKassenbuchPath = $tempDir . $kassenbuchFilename;

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($kassenbuchExcel);
            $writer->save($tempKassenbuchPath);
            $zip->addFile($tempKassenbuchPath, $kassenbuchFilename);

            // 2. Beleg-Dateien hinzufügen (nur Buchungen mit Beleg)
            $buchungenMitBelegen = array_values(array_filter($buchungen, fn ($b) => !empty($b['beleg_id']) && !empty($b['dateipfad'])));
            $fehlgeschlagen = [];

            foreach ($buchungenMitBelegen as $index => $buchung) {
                $originalDatei = FCPATH . $buchung['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen[] = $buchung['belegnummer'];
                    continue;
                }

                $zip->addFile($originalDatei, 'Belege/' . $this->generiereBelegDateiname($buchung, $index + 1));
            }

            // 3. Hinweis-Datei bei fehlenden Dateien
            if (!empty($fehlgeschlagen)) {
                $hinweis = "Folgende Beleg-Dateien wurden nicht gefunden und übersprungen:\n- "
                    . implode("\n- ", $fehlgeschlagen)
                    . "\nDas Kassenbuch-Excel enthält trotzdem alle Buchungen.\n";
                $zip->addFromString('00_Hinweise.txt', $hinweis);
            }

            $zip->close();

            if (file_exists($tempKassenbuchPath)) {
                unlink($tempKassenbuchPath);
            }

            $filename = $this->generiereExportFilename('Kassenbuch_komplett', $filter, 'zip');

            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setHeader('Content-Type', 'application/zip');
        } catch (\Exception $e) {
            log_message('error', 'Kassenbuch ZIP-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Erstellen der ZIP-Datei: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Beleg-Details für Auswahl
     */
    public function getBelegDetails($belegId)
    {
        $beleg = $this->belegModel->find($belegId);

        if (!$beleg) {
            return $this->response->setJSON(['error' => 'Beleg nicht gefunden'])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'belegnummer' => $beleg['belegnummer'],
            'beschreibung' => $beleg['beschreibung'],
            'betrag' => $beleg['betrag'],
            'lieferant' => $beleg['lieferant'],
        ]);
    }

    // ==================== PRIVATE HELPER METHODS ====================

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
            'suche' => $this->request->getGet('suche'),
        ];
    }

    /**
     * Generiert Dateiname für Beleg im Kassenbuch-ZIP
     */
    private function generiereBelegDateiname($buchung, $laufendeNummer)
    {
        $prefix = str_pad($laufendeNummer, 2, '0', STR_PAD_LEFT);
        $datum = date('Y-m-d', strtotime($buchung['buchungsdatum']));

        $beschreibung = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', $buchung['beschreibung']);
        $beschreibung = preg_replace('/\s+/', '_', trim($beschreibung));
        $beschreibung = rtrim(substr($beschreibung, 0, 20), '_');

        $extension = pathinfo($buchung['dateipfad'], PATHINFO_EXTENSION);

        return "{$prefix}_{$datum}_{$buchung['belegnummer']}_{$beschreibung}.{$extension}";
    }

    /**
     * Generiert Export-Dateiname basierend auf Filter
     */
    private function generiereExportFilename($prefix, $filter, $extension)
    {
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = $filter['datum_von'] . '_bis_' . $filter['datum_bis'];
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . $filter['datum_von'];
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . $filter['datum_bis'];
        }

        if (!empty($filter['konto_typ'])) {
            $parts[] = $filter['konto_typ'];
        }

        $filename = $prefix . (empty($parts) ? '' : '_' . implode('_', $parts));

        return $filename . '_' . date('Y-m-d') . '.' . $extension;
    }
}
