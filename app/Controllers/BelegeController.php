<?php

namespace App\Controllers;

use App\Libraries\BelegUpload;
use App\Models\AbrechnungBelegModel;
use App\Models\BelegModel;
use App\Models\BuchungModel;
use App\Models\PersonModel;
use App\Models\SchuldModel;

/**
 * BelegeController - Kern-Controller für Beleg-Verwaltung
 *
 * - Upload und Verwaltung von Belegen (PDF, Bilder)
 * - Automatische Belegnummer-Generierung
 * - Vorschau, Download und Export von Belegen
 */
class BelegeController extends BaseController
{
    protected $belegModel;
    protected $abrechnungBelegModel;
    protected $buchungModel;

    public function __construct()
    {
        $this->belegModel = new BelegModel();
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
        $this->buchungModel = new BuchungModel();
    }

    /**
     * Übersicht aller Belege
     */
    public function index()
    {
        $filter = $this->getFilterFromRequest();

        $belege = $this->belegModel->sucheBelege($filter);

        // Abrechnungs-Zuordnungen für alle Belege in 2 Queries laden (statt 2·N)
        $abrechnungenMap = $this->abrechnungBelegModel->getAbrechnungenFuerBelege(
            array_column($belege, 'id')
        );

        foreach ($belege as &$beleg) {
            $beleg['abrechnungen'] = $abrechnungenMap[$beleg['id']] ?? [];
        }
        unset($beleg);

        $data = [
            'title' => 'Belege-Übersicht',
            'belege' => $belege,
            'filter' => $filter,
            'stats' => $this->belegModel->getDashboardStats(),
            'kategorien' => kategorie_optionen(),
            'status_optionen' => beleg_status_optionen(),
        ];

        return view('belege/index', $data);
    }

    /**
     * Formular für neuen Beleg
     */
    public function create()
    {
        $data = [
            'title' => 'Neuen Beleg hinzufügen',
            'kategorien' => kategorie_optionen(),
            'max_upload_size' => BelegUpload::maxUploadSizeMb(),
            'personen_namen' => (new PersonModel())->getAnzeigenamen(),
        ];

        return view('belege/create', $data);
    }

    /**
     * Speichert neuen Beleg mit Datei-Upload
     */
    public function store()
    {
        $daten = $this->request->getPost();
        $daten['betrag'] = normalisiere_betrag($daten['betrag'] ?? null);
        $daten['erstattung_person'] = trim(preg_replace('/\s+/', ' ', $daten['erstattung_person'] ?? ''));

        $validation = \Config\Services::validation();
        $validation->setRules([
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]',
            'erstattung_person' => 'permit_empty|min_length[2]|max_length[100]',
            'beleg_datei' => 'uploaded[beleg_datei]|max_size[beleg_datei,10240]|ext_in[beleg_datei,pdf,jpg,jpeg,png]|mime_in[beleg_datei,application/pdf,image/jpeg,image/png,image/pjpeg]',
        ]);

        if (!$validation->run($daten)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $file = $this->request->getFile('beleg_datei');

        if (!$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Datei-Upload: ' . $file->getErrorString());
        }

        try {
            $upload = new BelegUpload($this->belegModel);
            $belegId = $upload->speichereBeleg($file, [
                'rechnungsdatum' => $daten['rechnungsdatum'],
                'beschreibung' => $daten['beschreibung'],
                'betrag' => $daten['betrag'],
                'lieferant' => $daten['lieferant'] ?: null,
                'erstattung_person' => $daten['erstattung_person'] ?: null,
                'kategorie' => $daten['kategorie'],
                'notizen' => $daten['notizen'] ?: null,
            ]);

            // Erstattung angegeben → Verbindlichkeit in der Schuldenliste anlegen
            (new SchuldModel())->syncBelegVerbindlichkeit($this->belegModel->find($belegId));

            return redirect()->to('/belege')->with('success', 'Beleg wurde erfolgreich erstellt!');
        } catch (\Exception $e) {
            log_message('error', 'Beleg-Upload Fehler: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern. Details stehen im Fehler-Log.');
        }
    }

    /**
     * Zeigt einen einzelnen Beleg mit Datei-Vorschau
     */
    public function show($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        $data = [
            'title' => 'Beleg ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'abrechnungen' => $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id),
            'kann_bearbeitet_werden' => $beleg['status'] === 'erfasst',
            'datei_existiert' => file_exists(FCPATH . $beleg['dateipfad']),
        ];

        return view('belege/show', $data);
    }

    /**
     * Bearbeitung eines Belegs
     */
    public function edit($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        if ($beleg['status'] !== 'erfasst') {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht bearbeitet werden (Status: ' . beleg_status_label($beleg['status']) . ')');
        }

        $data = [
            'title' => 'Beleg bearbeiten: ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'kategorien' => kategorie_optionen(),
            'personen_namen' => (new PersonModel())->getAnzeigenamen(),
        ];

        return view('belege/edit', $data);
    }

    /**
     * Aktualisiert einen Beleg
     */
    public function update($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg || $beleg['status'] !== 'erfasst') {
            return redirect()->to('/belege')->with('error', 'Beleg kann nicht bearbeitet werden.');
        }

        $daten = $this->request->getPost();
        $daten['betrag'] = normalisiere_betrag($daten['betrag'] ?? null);
        $daten['erstattung_person'] = trim(preg_replace('/\s+/', ' ', $daten['erstattung_person'] ?? ''));

        $validation = \Config\Services::validation();
        $validation->setRules([
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]',
            'erstattung_person' => 'permit_empty|min_length[2]|max_length[100]',
        ]);

        if (!$validation->run($daten)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $neuesRechnungsdatum = $daten['rechnungsdatum'];
        $datumGeaendert = $beleg['rechnungsdatum'] !== $neuesRechnungsdatum;

        $updateData = [
            'rechnungsdatum' => $neuesRechnungsdatum,
            'beschreibung' => $daten['beschreibung'],
            'betrag' => $daten['betrag'],
            'lieferant' => $daten['lieferant'] ?: null,
            'erstattung_person' => $daten['erstattung_person'] ?: null,
            'kategorie' => $daten['kategorie'],
            'notizen' => $daten['notizen'] ?: null,
        ];

        // Bei Datum-Änderung: neue Belegnummer generieren und Datei verschieben
        if ($datumGeaendert) {
            $neue_belegnummer = $this->belegModel->generiereNaechsteBelegnummer($neuesRechnungsdatum);

            if ($this->belegModel->belegnummerExistiert($neue_belegnummer, $id)) {
                return redirect()->back()->withInput()->with('error', 'Belegnummer für das neue Datum ist bereits vergeben.');
            }

            if ($this->verschiebeBeleg($beleg, $neue_belegnummer, $neuesRechnungsdatum)) {
                $updateData['belegnummer'] = $neue_belegnummer;
                $updateData['dateiname_system'] = $neue_belegnummer . '.' . $beleg['dateityp'];
                $updateData['dateipfad'] = $this->belegModel->generiereDateipfad($neuesRechnungsdatum) . $updateData['dateiname_system'];
            } else {
                return redirect()->back()->withInput()->with('error', 'Fehler beim Verschieben der Datei.');
            }
        }

        if ($this->belegModel->update($id, $updateData)) {
            $belegnummer = $datumGeaendert ? $updateData['belegnummer'] : $beleg['belegnummer'];

            // Verbindlichkeit in der Schuldenliste nachziehen (anlegen/ändern/entfernen)
            (new SchuldModel())->syncBelegVerbindlichkeit($this->belegModel->find($id));

            return redirect()->to("/belege/show/{$id}")
                ->with('success', "Beleg {$belegnummer} wurde erfolgreich aktualisiert!");
        }

        return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern der Änderungen.');
    }

    /**
     * Löscht einen Beleg (nur wenn nicht in Abrechnungen oder Buchungen verwendet)
     */
    public function delete($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            return redirect()->to('/belege')->with('error', 'Beleg nicht gefunden.');
        }

        $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id);

        if (!empty($abrechnungen)) {
            $abrechnungsListe = [];
            foreach ($abrechnungen as $abrechnung) {
                $abrechnungsListe[] = $abrechnung['titel'] . ' (' . strtoupper($abrechnung['typ']) . ')';
            }

            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er in folgenden Abrechnungen verwendet wird: ' . implode(', ', $abrechnungsListe));
        }

        $buchungen = $this->buchungModel->where('beleg_id', $id)->findAll();
        if (!empty($buchungen)) {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er mit einer Buchung verknüpft ist. Löschen Sie zuerst die Buchung.');
        }

        try {
            $dateipfad = FCPATH . $beleg['dateipfad'];
            if (file_exists($dateipfad) && !unlink($dateipfad)) {
                log_message('warning', 'Datei konnte nicht gelöscht werden: ' . $dateipfad);
            }

            if ($this->belegModel->delete($id)) {
                return redirect()->to('/belege')
                    ->with('success', "Beleg {$beleg['belegnummer']} und die zugehörige Datei wurden erfolgreich gelöscht!");
            }

            return redirect()->to('/belege')->with('error', 'Fehler beim Löschen des Belegs aus der Datenbank.');
        } catch (\Exception $e) {
            log_message('error', 'Beleg-Löschung Fehler: ' . $e->getMessage());

            return redirect()->to('/belege')->with('error', 'Ein Fehler ist beim Löschen aufgetreten. Details stehen im Fehler-Log.');
        }
    }

    /**
     * Download der Original-Datei
     */
    public function download($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        $dateipfad = FCPATH . $beleg['dateipfad'];

        if (!file_exists($dateipfad)) {
            return redirect()->back()->with('error', 'Datei nicht gefunden.');
        }

        return $this->response->download($dateipfad, null, true)
            ->setFileName($beleg['dateiname_system']);
    }

    /**
     * Streamt die Datei für die Inline-Vorschau
     */
    public function preview($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        $dateipfad = FCPATH . $beleg['dateipfad'];

        if (!file_exists($dateipfad)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Datei nicht gefunden');
        }

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];
        $mimeType = $mimeTypes[$beleg['dateityp']] ?? 'application/octet-stream';

        return $this->response->setHeader('Content-Type', $mimeType)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($dateipfad));
    }

    /**
     * Exportiert gefilterte Belege als Excel
     */
    public function exportExcel()
    {
        $filter = $this->getFilterFromRequest();
        $belege = $this->belegModel->sucheBelege($filter);

        if (empty($belege)) {
            return redirect()->back()->with('error', 'Keine Belege zum Exportieren gefunden.');
        }

        try {
            $excel = $this->erstelleBelegeExcel($belege);
            $filename = $this->generiereExportFilename('Belege_Export', $filter, 'xlsx');

            return \App\Helpers\ExcelHelper::downloadExcel($excel, $filename);
        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Excel-Export. Details stehen im Fehler-Log.');
        }
    }

    /**
     * Exportiert gefilterte Belege als ZIP mit Excel-Datei und allen Beleg-Dateien
     */
    public function exportZip()
    {
        $filter = $this->getFilterFromRequest();
        $belege = $this->belegModel->sucheBelege($filter);

        if (empty($belege)) {
            return redirect()->back()->with('error', 'Keine Belege zum Exportieren gefunden.');
        }

        try {
            \App\Helpers\ZipHelper::cleanupTempZips();

            $tempDir = WRITEPATH . 'temp/zip/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipPath = $tempDir . 'belege_komplett_' . uniqid('', true) . '.zip';

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden.');
            }

            // 1. Excel-Datei erstellen und hinzufügen
            $excel = $this->erstelleBelegeExcel($belege);
            $excelFilename = 'Belege_Liste_' . date('Y-m-d') . '.xlsx';
            $tempExcelPath = $tempDir . uniqid('temp_', true) . '.xlsx';

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
            $writer->save($tempExcelPath);
            $zip->addFile($tempExcelPath, $excelFilename);

            // 2. Alle Beleg-Dateien hinzufügen
            $fehlgeschlagen = [];

            foreach ($belege as $index => $beleg) {
                $originalDatei = FCPATH . $beleg['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen[] = $beleg['belegnummer'];
                    continue;
                }

                $zip->addFile($originalDatei, 'Belege/' . $this->generiereZipDateiname($beleg, $index + 1));
            }

            // 3. Hinweis-Datei bei fehlenden Dateien
            if (!empty($fehlgeschlagen)) {
                $hinweis = "Folgende Beleg-Dateien wurden nicht gefunden und übersprungen:\n- "
                    . implode("\n- ", $fehlgeschlagen)
                    . "\nDie Excel-Liste enthält trotzdem alle Belege.\n";
                $zip->addFromString('00_Hinweise.txt', $hinweis);
            }

            $zip->close();

            if (file_exists($tempExcelPath)) {
                unlink($tempExcelPath);
            }

            $filename = $this->generiereExportFilename('Belege_mit_Dateien', $filter, 'zip');

            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setHeader('Content-Type', 'application/zip');
        } catch (\Exception $e) {
            log_message('error', 'Belege ZIP-Export Fehler: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Fehler beim Erstellen der ZIP-Datei. Details stehen im Fehler-Log.');
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Extrahiert Filter aus Request
     */
    private function getFilterFromRequest()
    {
        return [
            'suche' => $this->request->getGet('suche'),
            'kategorie' => $this->request->getGet('kategorie'),
            'status' => $this->request->getGet('status'),
            'datum_von' => $this->request->getGet('datum_von'),
            'datum_bis' => $this->request->getGet('datum_bis'),
            'betrag_min' => $this->request->getGet('betrag_min'),
            'betrag_max' => $this->request->getGet('betrag_max'),
        ];
    }

    /**
     * Verschiebt Beleg-Datei bei Datum-Änderung
     */
    private function verschiebeBeleg($beleg, $neue_belegnummer, $neues_datum)
    {
        $alter_pfad = FCPATH . $beleg['dateipfad'];
        $neuer_pfad = FCPATH . $this->belegModel->generiereDateipfad($neues_datum);
        $neuer_vollpfad = $neuer_pfad . $neue_belegnummer . '.' . $beleg['dateityp'];

        if (file_exists($neuer_vollpfad)) {
            log_message('error', 'Beleg-Verschiebung: Zieldatei existiert bereits: ' . $neuer_vollpfad);
            return false;
        }

        try {
            BelegUpload::erstelleVerzeichnis($this->belegModel->generiereDateipfad($neues_datum));

            return rename($alter_pfad, $neuer_vollpfad);
        } catch (\Exception $e) {
            log_message('error', 'Beleg-Verschiebung Fehler: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Erstellt Excel-Datei für Belege-Export
     */
    private function erstelleBelegeExcel($belege)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Belege Export');

        $headers = [
            'A1' => 'Belegnummer',
            'B1' => 'Rechnungsdatum',
            'C1' => 'Eingabedatum',
            'D1' => 'Beschreibung',
            'E1' => 'Betrag',
            'F1' => 'Bezugsquelle',
            'G1' => 'Kategorie',
            'H1' => 'Status',
            'I1' => 'Notizen',
            'J1' => 'Dateityp',
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        $row = 2;
        foreach ($belege as $beleg) {
            $sheet->setCellValue('A' . $row, $beleg['belegnummer']);
            $sheet->setCellValue('B' . $row,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($beleg['rechnungsdatum'])));
            $sheet->setCellValue('C' . $row,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($beleg['eingabedatum'])));
            $sheet->setCellValue('D' . $row, $beleg['beschreibung']);
            $sheet->setCellValue('E' . $row, $beleg['betrag']);
            $sheet->setCellValue('F' . $row, $beleg['lieferant'] ?: '-');
            $sheet->setCellValue('G' . $row, kategorie_label($beleg['kategorie']));
            $sheet->setCellValue('H' . $row, beleg_status_label($beleg['status']));
            $sheet->setCellValue('I' . $row, $beleg['notizen'] ?: '-');
            $sheet->setCellValue('J' . $row, strtoupper($beleg['dateityp']));
            $row++;
        }

        $this->formatiereBelegeExcel($sheet, $row - 1);

        return $spreadsheet;
    }

    /**
     * Formatiert Excel-Tabelle für Belege
     */
    private function formatiereBelegeExcel($sheet, $lastRow)
    {
        $breiten = ['A' => 18, 'B' => 12, 'C' => 12, 'D' => 40, 'E' => 12, 'F' => 25, 'G' => 15, 'H' => 15, 'I' => 30, 'J' => 10];
        foreach ($breiten as $spalte => $breite) {
            $sheet->getColumnDimension($spalte)->setWidth($breite);
        }

        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:J1')->getFill()->getStartColor()->setRGB('DDDDDD');

        $sheet->getStyle('B2:C' . $lastRow)->getNumberFormat()->setFormatCode('DD.MM.YYYY');
        $sheet->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');

        $sheet->getStyle('A1:J' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $gesamtRow = $lastRow + 2;
        $sheet->setCellValue('D' . $gesamtRow, 'GESAMTSUMME:');
        $sheet->setCellValue('E' . $gesamtRow, '=SUM(E2:E' . $lastRow . ')');
        $sheet->getStyle('D' . $gesamtRow . ':E' . $gesamtRow)->getFont()->setBold(true);
        $sheet->getStyle('E' . $gesamtRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');
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

        if (!empty($filter['kategorie'])) {
            $parts[] = $filter['kategorie'];
        }

        if (!empty($filter['status'])) {
            $parts[] = $filter['status'];
        }

        $filename = $prefix . '_' . (empty($parts) ? 'alle' : implode('_', $parts));

        return $filename . '_' . date('Y-m-d') . '.' . $extension;
    }

    /**
     * Generiert aussagekräftigen Dateinamen für Belege im ZIP
     * Format: 01_2024-06-15-001_Beschreibung.pdf
     */
    private function generiereZipDateiname($beleg, $laufendeNummer)
    {
        $prefix = str_pad($laufendeNummer, 2, '0', STR_PAD_LEFT);

        $beschreibung = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', $beleg['beschreibung']);
        $beschreibung = preg_replace('/\s+/', '_', trim($beschreibung));
        $beschreibung = rtrim(substr($beschreibung, 0, 30), '_');

        $dateiname = "{$prefix}_{$beleg['belegnummer']}";
        if (!empty($beschreibung)) {
            $dateiname .= "_{$beschreibung}";
        }

        return $dateiname . '.' . $beleg['dateityp'];
    }
}
