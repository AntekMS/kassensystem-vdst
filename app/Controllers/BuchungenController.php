<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BuchungModel;
use App\Models\BelegModel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * BuchungenController - Kassenbuch-Verwaltung
 * ERWEITERT um Export-Funktionen für gefilterte Buchungen
 * ERWEITERT um direkten Beleg-Upload bei Buchungserstellung
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
     * Buchung speichern - ERWEITERT: Mit direktem Beleg-Upload
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

        $belegOption = $this->request->getPost('beleg_option');
        $belegId = null;

        // BELEG-UPLOAD VERARBEITEN (wenn Option "beleg_upload" gewählt)
        if ($belegOption === 'beleg_upload' && $this->request->getFile('beleg_datei')) {
            $file = $this->request->getFile('beleg_datei');

            // Prüfe ob Datei hochgeladen wurde
            if ($file->isValid() && !$file->hasMoved()) {
                // Validierung für Upload
                $uploadRules = [
                    'beleg_datei' => 'uploaded[beleg_datei]|max_size[beleg_datei,10240]|ext_in[beleg_datei,pdf,jpg,jpeg,png]',
                    'beleg_rechnungsdatum' => 'required|valid_date'
                ];

                if (!$this->validate($uploadRules)) {
                    return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
                }

                // Beleg erstellen
                try {
                    $belegId = $this->erstelleNeuenBeleg($file);

                    if (!$belegId) {
                        return redirect()->back()->withInput()->with('error', 'Fehler beim Erstellen des Belegs.');
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Beleg-Upload bei Buchung fehlgeschlagen: ' . $e->getMessage());
                    return redirect()->back()->withInput()->with('error', 'Fehler beim Beleg-Upload: ' . $e->getMessage());
                }
            }
        } elseif ($belegOption === 'beleg_waehlen') {
            // Bestehenden Beleg verwenden
            $belegId = $this->request->getPost('beleg_id') ?: null;
        }

        // Buchungs-Daten zusammenstellen
        $data = [
            'beleg_id' => $belegId,
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
     * Erstellt einen neuen Beleg aus Datei-Upload
     * Ähnlich wie BelegeController::store(), aber vereinfacht
     *
     * @param \CodeIgniter\Files\File $file
     * @return int|false Beleg-ID oder false bei Fehler
     */
    private function erstelleNeuenBeleg($file)
    {
        // Datei-Informationen VOR dem Verschieben sammeln
        $originalName = $file->getName();
        $fileSize = $file->getSize();
        $extension = $file->getExtension();

        // MIME-Type bestimmen
        try {
            $mimeType = $file->getMimeType();
        } catch (\Exception $e) {
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png'
            ];
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
        }

        // Beleg-Daten aus Formular
        $rechnungsdatum = $this->request->getPost('beleg_rechnungsdatum');
        $lieferant = $this->request->getPost('beleg_lieferant') ?: null;
        $kategorie = $this->request->getPost('beleg_kategorie') ?: 'normal';

        // Beschreibung und Betrag von der Buchung übernehmen
        $beschreibung = $this->request->getPost('beschreibung');
        $betrag = $this->request->getPost('betrag');

        // Belegnummer generieren
        $belegnummer = $this->belegModel->generiereNaechsteBelegnummer($rechnungsdatum);

        if ($this->belegModel->belegnummerExistiert($belegnummer)) {
            throw new \Exception('Belegnummer existiert bereits. Bitte versuchen Sie es erneut.');
        }

        // Dateipfad und Namen generieren
        $dateipfad = $this->belegModel->generiereDateipfad($rechnungsdatum);
        $systemDateiname = $this->belegModel->generiereSystemDateiname($belegnummer, $extension);
        $vollstaendigerPfad = $dateipfad . $systemDateiname;

        // Verzeichnis erstellen falls nicht vorhanden
        $this->erstelleVerzeichnisStruktur($dateipfad);

        // Datei verschieben
        $file->move(FCPATH . $dateipfad, $systemDateiname);

        // Beleg-Daten für Datenbank
        $belegData = [
            'belegnummer' => $belegnummer,
            'rechnungsdatum' => $rechnungsdatum,
            'eingabedatum' => date('Y-m-d'),
            'beschreibung' => $beschreibung,
            'betrag' => $betrag,
            'lieferant' => $lieferant,
            'dateiname_original' => $originalName,
            'dateiname_system' => $systemDateiname,
            'dateipfad' => $vollstaendigerPfad,
            'dateityp' => strtolower($extension),
            'dateigroesse' => $fileSize,
            'kategorie' => $kategorie,
            'status' => 'erfasst',
            'notizen' => 'Automatisch erstellt bei Buchung'
        ];

        // In Datenbank speichern
        $belegId = $this->belegModel->insert($belegData);

        if (!$belegId) {
            // Datei wieder löschen bei DB-Fehler
            if (file_exists(FCPATH . $vollstaendigerPfad)) {
                unlink(FCPATH . $vollstaendigerPfad);
            }
            return false;
        }

        return $belegId;
    }

    /**
     * Erstellt Verzeichnisstruktur (Helper)
     */
    private function erstelleVerzeichnisStruktur($pfad)
    {
        $vollstaendigerPfad = FCPATH . $pfad;

        if (!is_dir($vollstaendigerPfad)) {
            mkdir($vollstaendigerPfad, 0755, true);
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

            $filename = $this->generiereKassenbuchExcelFilename($filter);

            \App\Helpers\ExcelHelper::downloadExcel($spreadsheet, $filename);

        } catch (\Exception $e) {
            log_message('error', 'Excel-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Excel-Export: ' . $e->getMessage());
        }
    }

    /**
     * NEU: Exportiert nur Excel-Liste der gefilterten Buchungen
     */
    public function exportExcelListe()
    {
        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);

        if (empty($buchungen)) {
            return redirect()->back()->with('error', 'Keine Buchungen zum Exportieren gefunden.');
        }

        // Excel mit Buchungen-Liste erstellen
        $excel = $this->erstelleBuchungenExcel($buchungen, $filter);

        // Dateiname generieren
        $filename = $this->generiereExcelListeFilename($filter);

        // Download
        \App\Helpers\ExcelHelper::downloadExcel($excel, $filename);
    }

    /**
     * NEU: Exportiert gefilterte Buchungen als ZIP mit Excel UND allen Beleg-Dateien
     */
    public function exportZip()
    {
        $filter = $this->getFilterFromRequest();
        $buchungen = $this->buchungModel->getBuchungenMitBelegen($filter);

        if (empty($buchungen)) {
            return redirect()->back()->with('error', 'Keine Buchungen zum Exportieren gefunden.');
        }

        try {
            // Temporäres Verzeichnis für ZIP
            $tempDir = WRITEPATH . 'temp/zip/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFilename = 'kassenbuch_komplett_' . time() . '.zip';
            $zipPath = $tempDir . $zipFilename;

            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden. Fehlercode: ' . $result);
            }

            // 1. KASSENBUCH-EXCEL ERSTELLEN UND HINZUFÜGEN
            $kontostaende = $this->buchungModel->berechneKontostaende();
            $kassenbuchExcel = \App\Helpers\ExcelHelper::erstelleKassenbuch($buchungen, $kontostaende, $filter);
            $kassenbuchFilename = 'Kassenbuch_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Kassenbuch-Excel temporär speichern
            $tempKassenbuchPath = $tempDir . $kassenbuchFilename;
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($kassenbuchExcel);
            $writer->save($tempKassenbuchPath);

            // Kassenbuch-Excel zur ZIP hinzufügen
            $zip->addFile($tempKassenbuchPath, $kassenbuchFilename);

            // 2. BUCHUNGEN-LISTE EXCEL ERSTELLEN UND HINZUFÜGEN
            $buchungenExcel = $this->erstelleBuchungenExcel($buchungen, $filter);
            $buchungenFilename = 'Buchungen_Liste_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Buchungen-Excel temporär speichern
            $tempBuchungenPath = $tempDir . $buchungenFilename;
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($buchungenExcel);
            $writer->save($tempBuchungenPath);

            // Buchungen-Excel zur ZIP hinzufügen
            $zip->addFile($tempBuchungenPath, $buchungenFilename);

            // 3. INFO-DATEI ERSTELLEN
            $infoContent = $this->erstelleKassenbuchInfoDatei($buchungen, $filter);
            $zip->addFromString('00_Kassenbuch_Export_Info.txt', $infoContent);

            // 4. ALLE BELEG-DATEIEN HINZUFÜGEN (nur die mit Belegen)
            $buchungenMitBelegen = array_filter($buchungen, fn($b) => !empty($b['beleg_id']));
            $erfolgreich = 0;
            $fehlgeschlagen = 0;

            foreach ($buchungenMitBelegen as $index => $buchung) {
                if (empty($buchung['dateipfad'])) {
                    continue;
                }

                $originalDatei = FCPATH . $buchung['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen++;
                    continue;
                }

                // Aussagekräftigen Dateinamen generieren
                $neuerDateiname = $this->generiereKassenbuchBelegDateiname($buchung, $index + 1);

                if ($zip->addFile($originalDatei, 'Belege/' . $neuerDateiname)) {
                    $erfolgreich++;
                } else {
                    log_message('error', "Beleg konnte nicht zur ZIP hinzugefügt werden: {$buchung['belegnummer']}");
                    $fehlgeschlagen++;
                }
            }

            // 5. ZUSAMMENFASSUNG HINZUFÜGEN
            if ($fehlgeschlagen > 0 || count($buchungenMitBelegen) > 0) {
                $fehlerInfo = "=== KASSENBUCH EXPORT-HINWEISE ===\n";
                $fehlerInfo .= "Buchungen mit Belegen: " . count($buchungenMitBelegen) . "\n";
                $fehlerInfo .= "Buchungen ohne Belege: " . (count($buchungen) - count($buchungenMitBelegen)) . "\n";
                $fehlerInfo .= "Erfolgreich exportiert: {$erfolgreich} Beleg-Dateien\n";
                $fehlerInfo .= "Fehlgeschlagen: {$fehlgeschlagen} Beleg-Dateien\n";
                $fehlerInfo .= "\nDie Excel-Dateien enthalten trotzdem alle Buchungen.\n";

                $zip->addFromString('00_Export_Hinweise.txt', $fehlerInfo);
            }

            $zip->close();

            // Temporäre Excel-Dateien löschen
            if (file_exists($tempKassenbuchPath)) {
                unlink($tempKassenbuchPath);
            }
            if (file_exists($tempBuchungenPath)) {
                unlink($tempBuchungenPath);
            }

            // ZIP-Datei zum Download anbieten
            $filename = $this->generiereKassenbuchZipFilename($filter);

            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setHeader('Content-Type', 'application/zip');

        } catch (\Exception $e) {
            log_message('error', 'Kassenbuch ZIP-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Erstellen der ZIP-Datei: ' . $e->getMessage());
        }
    }

    /**
     * Erstellt Excel-Datei für Buchungen-Liste
     */
    private function erstelleBuchungenExcel($buchungen, $filter)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Buchungen Liste');

        // Header setzen
        $headers = [
            'A1' => 'Datum',
            'B1' => 'Beschreibung',
            'C1' => 'Belegnummer',
            'D1' => 'Konto',
            'E1' => 'Buchungsart',
            'F1' => 'Betrag',
            'G1' => 'Bezugsquelle',
            'H1' => 'Notizen'
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        // Daten eintragen
        $row = 2;
        foreach ($buchungen as $buchung) {
            $sheet->setCellValue('A' . $row,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($buchung['buchungsdatum'])));
            $sheet->setCellValue('B' . $row, $buchung['beschreibung']);
            $sheet->setCellValue('C' . $row, $buchung['belegnummer'] ?: 'ohne Beleg');
            $sheet->setCellValue('D' . $row, $this->getKontoLabel($buchung['konto_typ']));
            $sheet->setCellValue('E' . $row, $this->getBuchungsartLabel($buchung['buchungsart']));
            $sheet->setCellValue('F' . $row, $buchung['betrag']);
            $sheet->setCellValue('G' . $row, $buchung['lieferant'] ?: '-');
            $sheet->setCellValue('H' . $row, $buchung['notizen'] ?: '-');
            $row++;
        }

        // Formatierung
        $this->formatiereBuchungenExcel($sheet, $row - 1);

        return $spreadsheet;
    }

    /**
     * Formatiert Excel-Tabelle für Buchungen
     */
    private function formatiereBuchungenExcel($sheet, $lastRow)
    {
        // Spaltenbreiten
        $sheet->getColumnDimension('A')->setWidth(12); // Datum
        $sheet->getColumnDimension('B')->setWidth(40); // Beschreibung
        $sheet->getColumnDimension('C')->setWidth(18); // Belegnummer
        $sheet->getColumnDimension('D')->setWidth(15); // Konto
        $sheet->getColumnDimension('E')->setWidth(12); // Buchungsart
        $sheet->getColumnDimension('F')->setWidth(12); // Betrag
        $sheet->getColumnDimension('G')->setWidth(25); // Bezugsquelle
        $sheet->getColumnDimension('H')->setWidth(30); // Notizen

        // Header-Formatierung
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:H1')->getFill()->getStartColor()->setRGB('DDDDDD');

        // Datum-Formatierung
        $sheet->getStyle('A2:A' . $lastRow)->getNumberFormat()->setFormatCode('DD.MM.YYYY');

        // Betrag-Formatierung
        $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');

        // Rahmen
        $sheet->getStyle('A1:H' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Gesamtsumme hinzufügen
        $gesamtRow = $lastRow + 2;
        $sheet->setCellValue('E' . $gesamtRow, 'GESAMTSUMME:');
        $sheet->setCellValue('F' . $gesamtRow, '=SUM(F2:F' . $lastRow . ')');
        $sheet->getStyle('E' . $gesamtRow . ':F' . $gesamtRow)->getFont()->setBold(true);
        $sheet->getStyle('F' . $gesamtRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');
    }

    /**
     * Erstellt Info-Datei für Kassenbuch-Export
     */
    private function erstelleKassenbuchInfoDatei($buchungen, $filter)
    {
        $info = "==============================================\n";
        $info .= "KASSENBUCH-EXPORT - KOMPLETTARCHIV\n";
        $info .= "==============================================\n\n";

        $info .= "Export erstellt:    " . date('d.m.Y H:i:s') . "\n";
        $info .= "Anzahl Buchungen:   " . count($buchungen) . "\n";

        $einnahmen = array_sum(array_map(fn($b) => $b['buchungsart'] === 'einnahme' ? $b['betrag'] : 0, $buchungen));
        $ausgaben = array_sum(array_map(fn($b) => $b['buchungsart'] === 'ausgabe' ? $b['betrag'] : 0, $buchungen));
        $saldo = $einnahmen - $ausgaben;

        $info .= "Einnahmen:          " . number_format($einnahmen, 2, ',', '.') . " €\n";
        $info .= "Ausgaben:           " . number_format($ausgaben, 2, ',', '.') . " €\n";
        $info .= "Saldo:              " . number_format($saldo, 2, ',', '.') . " €\n\n";

        // Filter-Informationen
        $info .= "ANGEWENDETE FILTER:\n";
        $info .= str_repeat('-', 50) . "\n";
        if (!empty($filter['datum_von'])) {
            $info .= "Von Datum:          " . date('d.m.Y', strtotime($filter['datum_von'])) . "\n";
        }
        if (!empty($filter['datum_bis'])) {
            $info .= "Bis Datum:          " . date('d.m.Y', strtotime($filter['datum_bis'])) . "\n";
        }
        if (!empty($filter['konto_typ'])) {
            $info .= "Konto:              " . $this->getKontoLabel($filter['konto_typ']) . "\n";
        }
        if (!empty($filter['buchungsart'])) {
            $info .= "Buchungsart:        " . $this->getBuchungsartLabel($filter['buchungsart']) . "\n";
        }
        if (!empty($filter['suche'])) {
            $info .= "Suchbegriff:        " . $filter['suche'] . "\n";
        }
        if (empty(array_filter($filter))) {
            $info .= "Keine Filter aktiv - Alle Buchungen exportiert\n";
        }

        $info .= "\nBUCHUNGEN-ÜBERSICHT:\n";
        $info .= str_repeat('=', 80) . "\n";
        $info .= sprintf("%-12s %-30s %-15s %-12s %s\n",
            "Datum", "Beschreibung", "Belegnummer", "Betrag", "Konto");
        $info .= str_repeat('-', 80) . "\n";

        foreach ($buchungen as $buchung) {
            $beschreibung = strlen($buchung['beschreibung']) > 30 ?
                substr($buchung['beschreibung'], 0, 27) . '...' :
                $buchung['beschreibung'];

            $info .= sprintf("%-12s %-30s %-15s %10s € %s\n",
                date('d.m.Y', strtotime($buchung['buchungsdatum'])),
                $beschreibung,
                $buchung['belegnummer'] ?: 'ohne Beleg',
                number_format($buchung['betrag'], 2, ',', '.'),
                $this->getKontoLabel($buchung['konto_typ']));
        }

        $info .= str_repeat('-', 80) . "\n";
        $info .= sprintf("%43s %10s € (Einnahmen)\n", "SUMME:",
            number_format($einnahmen, 2, ',', '.'));
        $info .= sprintf("%43s %10s € (Ausgaben)\n", "",
            number_format($ausgaben, 2, ',', '.'));
        $info .= sprintf("%43s %10s € (SALDO)\n", "",
            number_format($saldo, 2, ',', '.'));

        $info .= "\n\nDATEI-STRUKTUR:\n";
        $info .= str_repeat('=', 50) . "\n";
        $info .= "- Kassenbuch_[Datum].xlsx (Original Kassenbuch-Format)\n";
        $info .= "- Buchungen_Liste_[Datum].xlsx (Detaillierte Buchungsliste)\n";
        $info .= "- 00_Kassenbuch_Export_Info.txt (diese Datei)\n";
        $info .= "- Belege/ (Ordner mit Beleg-Dateien, nur bei Buchungen mit Belegen)\n";

        $buchungenMitBelegen = array_filter($buchungen, fn($b) => !empty($b['beleg_id']));
        foreach ($buchungenMitBelegen as $index => $buchung) {
            $dateiname = $this->generiereKassenbuchBelegDateiname($buchung, $index + 1);
            $info .= "  - {$dateiname}\n";
        }

        return $info;
    }

    /**
     * Generiert Dateiname für Beleg in Kassenbuch-ZIP
     */
    private function generiereKassenbuchBelegDateiname($buchung, $laufendeNummer)
    {
        $prefix = str_pad($laufendeNummer, 2, '0', STR_PAD_LEFT);
        $datum = date('Y-m-d', strtotime($buchung['buchungsdatum']));
        $belegnummer = $buchung['belegnummer'] ?: 'ohne_beleg';

        // Beschreibung für Dateiname vorbereiten
        $beschreibung = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', $buchung['beschreibung']);
        $beschreibung = preg_replace('/\s+/', '_', trim($beschreibung));
        $beschreibung = substr($beschreibung, 0, 20);
        $beschreibung = rtrim($beschreibung, '_');

        // Dateiendung aus Pfad extrahieren
        $extension = pathinfo($buchung['dateipfad'], PATHINFO_EXTENSION);

        return "{$prefix}_{$datum}_{$belegnummer}_{$beschreibung}.{$extension}";
    }

    /**
     * Generiert Kassenbuch-Excel-Dateiname
     */
    private function generiereKassenbuchExcelFilename($filter)
    {
        $prefix = 'Kassenbuch';
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = date('Y-m-d', strtotime($filter['datum_von'])) . '_bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . date('Y-m-d', strtotime($filter['datum_von']));
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        }

        if (!empty($filter['konto_typ'])) {
            $parts[] = $filter['konto_typ'];
        }

        if (!empty($parts)) {
            $filename = $prefix . '_' . implode('_', $parts);
        } else {
            $filename = $prefix;
        }

        return $filename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    /**
     * Generiert Excel-Liste-Dateiname
     */
    private function generiereExcelListeFilename($filter)
    {
        $prefix = 'Buchungen_Liste';
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = date('Y-m-d', strtotime($filter['datum_von'])) . '_bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . date('Y-m-d', strtotime($filter['datum_von']));
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        }

        if (!empty($filter['konto_typ'])) {
            $parts[] = $filter['konto_typ'];
        }

        if (!empty($parts)) {
            $filename = $prefix . '_' . implode('_', $parts);
        } else {
            $filename = $prefix . '_alle';
        }

        return $filename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    /**
     * Generiert ZIP-Dateiname für Kassenbuch
     */
    private function generiereKassenbuchZipFilename($filter)
    {
        $prefix = 'Kassenbuch_komplett';
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = date('Y-m', strtotime($filter['datum_von'])) . '_bis_' . date('Y-m', strtotime($filter['datum_bis']));
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . date('Y-m', strtotime($filter['datum_von']));
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . date('Y-m', strtotime($filter['datum_bis']));
        }

        if (!empty($filter['konto_typ'])) {
            $parts[] = $filter['konto_typ'];
        }

        if (!empty($parts)) {
            $filename = $prefix . '_' . implode('_', $parts);
        } else {
            $filename = $prefix . '_alle';
        }

        return $filename . '_' . date('Y-m-d') . '.zip';
    }

    /**
     * Konvertiert Konto-Typ zu Label
     */
    private function getKontoLabel($konto)
    {
        $labels = [
            'aktivenkasse' => 'Aktivenkasse',
            'getraenkekasse' => 'Getränkekasse',
            'barkasse' => 'Barkasse'
        ];
        return $labels[$konto] ?? $konto;
    }

    /**
     * Konvertiert Buchungsart zu Label
     */
    private function getBuchungsartLabel($buchungsart)
    {
        return $buchungsart === 'einnahme' ? 'Einnahme' : 'Ausgabe';
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