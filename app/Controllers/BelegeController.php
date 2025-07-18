<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BelegModel;
use App\Models\AbrechnungBelegModel;
use App\Models\BuchungModel;
use CodeIgniter\Files\File;

/**
 * BelegeController - Kern-Controller für Beleg-Verwaltung
 *
 * Wie ein intelligenter Aktenverwalter:
 * - Upload und Verwaltung von Belegen (PDF, Bilder)
 * - Automatische Belegnummer-Generierung
 * - Datei-Umbenennung und Organisation
 * - Vorschau und Download von Belegen
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
     * Wie ein Aktenordner mit Suchfunktion
     */
    public function index()
    {
        $filter = $this->getFilterFromRequest();

        // Belege basierend auf Filter holen
        $belege = $this->belegModel->sucheBelege($filter);

        // Für jeden Beleg prüfen ob er in Abrechnungen ist
        foreach ($belege as &$beleg) {
            $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($beleg['id']);
            $beleg['abrechnungen'] = $abrechnungen;
        }

        $data = [
            'title' => 'Belege-Übersicht',
            'belege' => $belege,
            'filter' => $filter,
            'stats' => $this->belegModel->getDashboardStats(),
            // Für Filter-Dropdowns
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ],
            'status_optionen' => [
                'erfasst' => 'Erfasst',
                'in_abrechnung' => 'In Abrechnung',
                'abgerechnet' => 'Abgerechnet',
                'bezahlt' => 'Bezahlt'
            ]
        ];

        return view('belege/index', $data);
    }

    /**
     * Formular für neuen Beleg
     * Wie ein Eingabe-Formular für neue Akten
     */
    public function create()
    {
        $data = [
            'title' => 'Neuen Beleg hinzufügen',
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ],
            'max_upload_size' => $this->getMaxUploadSize()
        ];

        return view('belege/create', $data);
    }

    /**
     * Speichert neuen Beleg mit Datei-Upload
     * Wie das Einheften eines neuen Dokuments
     */
    /**
     * Speichert neuen Beleg mit Datei-Upload - KORRIGIERTE VERSION
     * Problem gelöst: Datei-Informationen werden VOR dem Verschieben gesammelt
     */
    public function store()
    {
        $validation = \Config\Services::validation();

        // Validierungs-Regeln
        $rules = [
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]',
            'beleg_datei' => 'uploaded[beleg_datei]|max_size[beleg_datei,10240]|ext_in[beleg_datei,pdf,jpg,jpeg,png]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $file = $this->request->getFile('beleg_datei');

        if (!$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Datei-Upload: ' . $file->getErrorString());
        }

        // *** WICHTIG: ALLE Datei-Informationen VOR dem Verschieben sammeln! ***
        $originalName = $file->getName();           // Original-Name
        $fileSize = $file->getSize();               // Dateigröße
        $extension = $file->getExtension();         // Dateiendung
        $tempPath = $file->getTempName();           // Temp-Pfad (nur für Debugging)

        // MIME-Type sicher bestimmen BEVOR die Datei verschoben wird
        try {
            $mimeType = $file->getMimeType(); // oder $file->getType()
        } catch (\Exception $e) {
            // Fallback basierend auf Dateiendung
            $mimeType = $this->getMimeTypeFromExtension($extension);
            log_message('warning', 'MIME-Type konnte nicht bestimmt werden, Fallback verwendet: ' . $mimeType);
        }

        // Belegnummer generieren
        $rechnungsdatum = $this->request->getPost('rechnungsdatum');
        $belegnummer = $this->belegModel->generiereNaechsteBelegnummer($rechnungsdatum);

        // Prüfe ob Belegnummer bereits existiert (Sicherheitscheck)
        if ($this->belegModel->belegnummerExistiert($belegnummer)) {
            return redirect()->back()->withInput()->with('error', 'Fehler bei der Belegnummer-Generierung. Bitte versuchen Sie es erneut.');
        }

        try {
            // Dateipfad und Namen generieren
            $dateipfad = $this->belegModel->generiereDateipfad($rechnungsdatum);
            $systemDateiname = $this->belegModel->generiereSystemDateiname($belegnummer, $extension);
            $vollstaendigerPfad = $dateipfad . $systemDateiname;

            // Verzeichnis erstellen falls nicht vorhanden
            $this->erstelleVerzeichnisStruktur($dateipfad);

            // *** JETZT ERST die Datei verschieben (nur EINMAL!) ***
            $file->move(FCPATH . $dateipfad, $systemDateiname);

            // *** NACH dem Verschieben KEINE weiteren Datei-Operationen auf $file! ***

            // Beleg-Daten für Datenbank vorbereiten (mit vorher gesammelten Informationen)
            $belegData = [
                'belegnummer' => $belegnummer,
                'rechnungsdatum' => $rechnungsdatum,
                'eingabedatum' => date('Y-m-d'),
                'beschreibung' => $this->request->getPost('beschreibung'),
                'betrag' => $this->request->getPost('betrag'),
                'lieferant' => $this->request->getPost('lieferant') ?: null,
                'dateiname_original' => $originalName,           // Vorher gesammelt
                'dateiname_system' => $systemDateiname,
                'dateipfad' => $vollstaendigerPfad,
                'dateityp' => strtolower($extension),            // Vorher gesammelt
                'dateigroesse' => $fileSize,                     // Vorher gesammelt
                'kategorie' => $this->request->getPost('kategorie'),
                'status' => 'erfasst',
                'notizen' => $this->request->getPost('notizen') ?: null
            ];

            // In Datenbank speichern
            $belegId = $this->belegModel->insert($belegData);

            if ($belegId) {
                $message = "Beleg {$belegnummer} wurde erfolgreich erstellt!";
                return redirect()->to('/belege')->with('success', $message);
            } else {
                // Datei wieder löschen bei DB-Fehler
                if (file_exists(FCPATH . $vollstaendigerPfad)) {
                    unlink(FCPATH . $vollstaendigerPfad);
                }
                return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern in der Datenbank.');
            }

        } catch (\Exception $e) {
            // Aufräumen bei Fehlern
            if (isset($vollstaendigerPfad) && file_exists(FCPATH . $vollstaendigerPfad)) {
                unlink(FCPATH . $vollstaendigerPfad);
            }

            log_message('error', 'Beleg-Upload Fehler: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Ein unerwarteter Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }

    /**
     * Fallback MIME-Type basierend auf Dateiendung
     * Für den Fall, dass getMimeType() fehlschlägt
     */
    private function getMimeTypeFromExtension($extension)
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Zeigt einen einzelnen Beleg mit Datei-Vorschau
     * Wie das Öffnen einer Akte zur Ansicht
     */
    public function show($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        // Abrechnungen für diesen Beleg holen
        $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id);

        $data = [
            'title' => 'Beleg ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'abrechnungen' => $abrechnungen,
            'kann_bearbeitet_werden' => $beleg['status'] === 'erfasst',
            'datei_existiert' => file_exists(FCPATH . $beleg['dateipfad'])
        ];

        return view('belege/show', $data);
    }

    /**
     * Bearbeitung eines Belegs
     * Wie das Überarbeiten einer Akte
     */
    public function edit($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        // Prüfe ob Beleg bearbeitet werden kann
        if ($beleg['status'] !== 'erfasst') {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht bearbeitet werden (Status: ' . $beleg['status'] . ')');
        }

        $data = [
            'title' => 'Beleg bearbeiten: ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ]
        ];

        return view('belege/edit', $data);
    }

    /**
     * Aktualisiert einen Beleg
     * Wie das Speichern von Änderungen an einer Akte
     */
    public function update($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg || $beleg['status'] !== 'erfasst') {
            return redirect()->to('/belege')->with('error', 'Beleg kann nicht bearbeitet werden.');
        }

        $validation = \Config\Services::validation();

        $rules = [
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        // Prüfe ob sich das Rechnungsdatum geändert hat
        $neuesRechnungsdatum = $this->request->getPost('rechnungsdatum');
        $datumGeaendert = $beleg['rechnungsdatum'] !== $neuesRechnungsdatum;

        $updateData = [
            'rechnungsdatum' => $neuesRechnungsdatum,
            'beschreibung' => $this->request->getPost('beschreibung'),
            'betrag' => $this->request->getPost('betrag'),
            'lieferant' => $this->request->getPost('lieferant') ?: null,
            'kategorie' => $this->request->getPost('kategorie'),
            'notizen' => $this->request->getPost('notizen') ?: null
        ];

        // Wenn Datum geändert wurde, neue Belegnummer generieren und Datei verschieben
        if ($datumGeaendert) {
            $neue_belegnummer = $this->belegModel->generiereNaechsteBelegnummer($neuesRechnungsdatum);

            if ($this->belegModel->belegnummerExistiert($neue_belegnummer, $id)) {
                return redirect()->back()->withInput()->with('error', 'Belegnummer für das neue Datum ist bereits vergeben.');
            }

            // Datei umbenennen und verschieben
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
            return redirect()->to("/belege/show/{$id}")
                ->with('success', "Beleg {$belegnummer} wurde erfolgreich aktualisiert!");
        } else {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern der Änderungen.');
        }
    }

    /**
     * Löscht einen Beleg (nur wenn noch nicht in Abrechnungen)
     * Wie das Vernichten einer Akte
     */
    public function delete($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            return redirect()->to('/belege')->with('error', 'Beleg nicht gefunden.');
        }

        // Prüfe ob Beleg in Abrechnungen verwendet wird
        $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id);

        if (!empty($abrechnungen)) {
            $abrechnungsListe = [];
            foreach ($abrechnungen as $abrechnung) {
                $abrechnungsListe[] = $abrechnung['titel'] . ' (' . strtoupper($abrechnung['typ']) . ')';
            }
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er in folgenden Abrechnungen verwendet wird: ' . implode(', ', $abrechnungsListe));
        }

        // Prüfe ob Beleg in Buchungen verwendet wird - KORRIGIERT
        $buchungen = $this->buchungModel->where('beleg_id', $id)->findAll();
        if (!empty($buchungen)) {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er mit einer Buchung verknüpft ist. Löschen Sie zuerst die Buchung.');
        }

        try {
            // Datei löschen
            $dateipfad = FCPATH . $beleg['dateipfad'];
            if (file_exists($dateipfad)) {
                if (!unlink($dateipfad)) {
                    log_message('warning', 'Datei konnte nicht gelöscht werden: ' . $dateipfad);
                }
            }

            // Aus Datenbank löschen
            if ($this->belegModel->delete($id)) {
                return redirect()->to('/belege')
                    ->with('success', "Beleg {$beleg['belegnummer']} und die zugehörige Datei wurden erfolgreich gelöscht!");
            } else {
                return redirect()->to('/belege')->with('error', 'Fehler beim Löschen des Belegs aus der Datenbank.');
            }

        } catch (\Exception $e) {
            log_message('error', 'Beleg-Löschung Fehler: ' . $e->getMessage());
            return redirect()->to('/belege')->with('error', 'Ein Fehler ist beim Löschen aufgetreten: ' . $e->getMessage());
        }
    }

    /**
     * Download der Original-Datei
     * Wie das Herausnehmen einer Kopie aus der Akte
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

        // Download mit System-Dateiname (z.B. "2024-06-15-001.pdf")
        return $this->response->download($dateipfad, null, true)
            ->setFileName($beleg['dateiname_system']);
    }

    /**
     * AJAX-Endpoint für Datei-Vorschau
     */
    public function preview($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            return $this->response->setJSON(['error' => 'Beleg nicht gefunden']);
        }

        $dateipfad = FCPATH . $beleg['dateipfad'];

        if (!file_exists($dateipfad)) {
            return $this->response->setJSON(['error' => 'Datei nicht gefunden']);
        }

        // MIME-Type NICHT über finfo ermitteln, sondern aus DB verwenden
        if ($beleg['dateityp'] === 'pdf') {
            $mimeType = 'application/pdf';
        } else {
            // Für Bilder basierend auf gespeichertem Dateityp
            $mimeType = 'image/' . $beleg['dateityp'];
        }

        return $this->response->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($dateipfad));
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
            'betrag_max' => $this->request->getGet('betrag_max')
        ];
    }

    /**
     * Erstellt Verzeichnisstruktur
     */
    private function erstelleVerzeichnisStruktur($pfad)
    {
        $vollstaendigerPfad = FCPATH . $pfad;

        if (!is_dir($vollstaendigerPfad)) {
            mkdir($vollstaendigerPfad, 0755, true);
        }
    }

    /**
     * Verschiebt Beleg-Datei bei Datum-Änderung
     */
    private function verschiebeBeleg($beleg, $neue_belegnummer, $neues_datum)
    {
        $alter_pfad = FCPATH . $beleg['dateipfad'];
        $neuer_pfad = FCPATH . $this->belegModel->generiereDateipfad($neues_datum);
        $neuer_dateiname = $neue_belegnummer . '.' . $beleg['dateityp'];
        $neuer_vollpfad = $neuer_pfad . $neuer_dateiname;

        try {
            $this->erstelleVerzeichnisStruktur($this->belegModel->generiereDateipfad($neues_datum));
            return rename($alter_pfad, $neuer_vollpfad);
        } catch (\Exception $e) {
            log_message('error', 'Beleg-Verschiebung Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt maximale Upload-Größe
     */
    private function getMaxUploadSize()
    {
        $max_upload = ini_get('upload_max_filesize');
        $max_post = ini_get('post_max_size');
        $memory_limit = ini_get('memory_limit');

        $upload_mb = $this->parseSize($max_upload);
        $post_mb = $this->parseSize($max_post);
        $memory_mb = $this->parseSize($memory_limit);

        return min($upload_mb, $post_mb, $memory_mb);
    }

    /**
     * Konvertiert Größenangaben zu MB
     */
    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])) / 1024 / 1024);
        } else {
            return round($size / 1024 / 1024);
        }
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

        // Excel mit ExcelHelper erstellen
        $excel = $this->erstelleBelegeExcel($belege, $filter);

        // Dateiname generieren
        $filename = $this->generiereExcelFilename($filter);

        // Download
        \App\Helpers\ExcelHelper::downloadExcel($excel, $filename);
    }

    /**
     * Exportiert gefilterte Belege als ZIP mit Excel-Datei UND allen Beleg-Dateien
     */
    public function exportZip()
    {
        $filter = $this->getFilterFromRequest();
        $belege = $this->belegModel->sucheBelege($filter);

        if (empty($belege)) {
            return redirect()->back()->with('error', 'Keine Belege zum Exportieren gefunden.');
        }

        try {
            // Temporäres Verzeichnis für ZIP
            $tempDir = WRITEPATH . 'temp/zip/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFilename = 'belege_komplett_' . time() . '.zip';
            $zipPath = $tempDir . $zipFilename;

            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden. Fehlercode: ' . $result);
            }

            // 1. EXCEL-DATEI ERSTELLEN UND HINZUFÜGEN
            $excel = $this->erstelleBelegeExcel($belege, $filter);
            $excelFilename = 'Belege_Liste_' . date('Y-m-d_H-i-s') . '.xlsx';

            // Excel temporär speichern
            $tempExcelPath = $tempDir . $excelFilename;
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($excel);
            $writer->save($tempExcelPath);

            // Excel zur ZIP hinzufügen
            $zip->addFile($tempExcelPath, $excelFilename);

            // 2. INFO-DATEI ERSTELLEN
            $infoContent = $this->erstelleInfoDatei($belege, $filter);
            $zip->addFromString('00_Export_Info.txt', $infoContent);

            // 3. ALLE BELEG-DATEIEN HINZUFÜGEN
            $erfolgreich = 0;
            $fehlgeschlagen = 0;

            foreach ($belege as $index => $beleg) {
                $originalDatei = FCPATH . $beleg['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen++;
                    continue;
                }

                // Aussagekräftigen Dateinamen generieren
                $neuerDateiname = $this->generiereZipDateiname($beleg, $index + 1);

                if ($zip->addFile($originalDatei, 'Belege/' . $neuerDateiname)) {
                    $erfolgreich++;
                } else {
                    log_message('error', "Beleg konnte nicht zur ZIP hinzugefügt werden: {$beleg['belegnummer']}");
                    $fehlgeschlagen++;
                }
            }

            // 4. ZUSAMMENFASSUNG HINZUFÜGEN
            if ($fehlgeschlagen > 0) {
                $fehlerInfo = "=== EXPORT-HINWEISE ===\n";
                $fehlerInfo .= "Erfolgreich exportiert: {$erfolgreich} Beleg-Dateien\n";
                $fehlerInfo .= "Fehlgeschlagen: {$fehlgeschlagen} Beleg-Dateien\n";
                $fehlerInfo .= "Fehlgeschlagene Belege wurden übersprungen.\n";
                $fehlerInfo .= "Die Excel-Liste enthält trotzdem alle Belege.\n";

                $zip->addFromString('00_Export_Hinweise.txt', $fehlerInfo);
            }

            $zip->close();

            // Temporäre Excel-Datei löschen
            if (file_exists($tempExcelPath)) {
                unlink($tempExcelPath);
            }

            if ($erfolgreich === 0 && count($belege) > 0) {
                throw new \Exception('Keine Beleg-Dateien konnten hinzugefügt werden, aber Excel-Liste wurde erstellt.');
            }

            // ZIP-Datei zum Download anbieten
            $filename = $this->generiereZipFilename($filter);

            return $this->response->download($zipPath, null, true)
                ->setFileName($filename)
                ->setHeader('Content-Type', 'application/zip');

        } catch (\Exception $e) {
            log_message('error', 'Belege ZIP-Export Fehler: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Fehler beim Erstellen der ZIP-Datei: ' . $e->getMessage());
        }
    }

    /**
     * Erstellt Excel-Datei für Belege-Export
     */
    private function erstelleBelegeExcel($belege, $filter)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Belege Export');

        // Header setzen
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
            'J1' => 'Dateityp'
        ];

        foreach ($headers as $cell => $header) {
            $sheet->setCellValue($cell, $header);
        }

        // Daten eintragen
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
            $sheet->setCellValue('G' . $row, $this->getKategorieLabel($beleg['kategorie']));
            $sheet->setCellValue('H' . $row, $this->getStatusLabel($beleg['status']));
            $sheet->setCellValue('I' . $row, $beleg['notizen'] ?: '-');
            $sheet->setCellValue('J' . $row, strtoupper($beleg['dateityp']));
            $row++;
        }

        // Formatierung
        $this->formatiereBelegeExcel($sheet, $row - 1);

        return $spreadsheet;
    }

    /**
     * Formatiert Excel-Tabelle für Belege
     */
    private function formatiereBelegeExcel($sheet, $lastRow)
    {
        // Spaltenbreiten
        $sheet->getColumnDimension('A')->setWidth(18); // Belegnummer
        $sheet->getColumnDimension('B')->setWidth(12); // Rechnungsdatum
        $sheet->getColumnDimension('C')->setWidth(12); // Eingabedatum
        $sheet->getColumnDimension('D')->setWidth(40); // Beschreibung
        $sheet->getColumnDimension('E')->setWidth(12); // Betrag
        $sheet->getColumnDimension('F')->setWidth(25); // Bezugsquelle
        $sheet->getColumnDimension('G')->setWidth(15); // Kategorie
        $sheet->getColumnDimension('H')->setWidth(15); // Status
        $sheet->getColumnDimension('I')->setWidth(30); // Notizen
        $sheet->getColumnDimension('J')->setWidth(10); // Dateityp

        // Header-Formatierung
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:J1')->getFill()->getStartColor()->setRGB('DDDDDD');

        // Datum-Formatierung
        $sheet->getStyle('B2:C' . $lastRow)->getNumberFormat()->setFormatCode('DD.MM.YYYY');

        // Betrag-Formatierung
        $sheet->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');

        // Rahmen
        $sheet->getStyle('A1:J' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Gesamtsumme hinzufügen
        $gesamtRow = $lastRow + 2;
        $sheet->setCellValue('D' . $gesamtRow, 'GESAMTSUMME:');
        $sheet->setCellValue('E' . $gesamtRow, '=SUM(E2:E' . $lastRow . ')');
        $sheet->getStyle('D' . $gesamtRow . ':E' . $gesamtRow)->getFont()->setBold(true);
        $sheet->getStyle('E' . $gesamtRow)->getNumberFormat()->setFormatCode('#,##0.00 "€"');
    }

    /**
     * Generiert Excel-Dateiname basierend auf Filter
     */
    private function generiereExcelFilename($filter)
    {
        $prefix = 'Belege_Export';
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = date('Y-m-d', strtotime($filter['datum_von'])) . '_bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . date('Y-m-d', strtotime($filter['datum_von']));
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . date('Y-m-d', strtotime($filter['datum_bis']));
        }

        if (!empty($filter['kategorie'])) {
            $parts[] = $filter['kategorie'];
        }

        if (!empty($filter['status'])) {
            $parts[] = $filter['status'];
        }

        if (!empty($filter['suche'])) {
            $suchbegriff = preg_replace('/[^a-zA-Z0-9]/', '_', $filter['suche']);
            $parts[] = 'suche_' . substr($suchbegriff, 0, 20);
        }

        if (!empty($parts)) {
            $filename = $prefix . '_' . implode('_', $parts);
        } else {
            $filename = $prefix . '_alle';
        }

        return $filename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    }

    /**
     * Generiert ZIP-Dateiname basierend auf Filter
     */
    private function generiereZipFilename($filter)
    {
        $prefix = 'Belege_mit_Dateien';
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = date('Y-m', strtotime($filter['datum_von'])) . '_bis_' . date('Y-m', strtotime($filter['datum_bis']));
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . date('Y-m', strtotime($filter['datum_von']));
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . date('Y-m', strtotime($filter['datum_bis']));
        }

        if (!empty($filter['kategorie'])) {
            $parts[] = $filter['kategorie'];
        }

        if (!empty($parts)) {
            $filename = $prefix . '_' . implode('_', $parts);
        } else {
            $filename = $prefix . '_alle';
        }

        return $filename . '_' . date('Y-m-d') . '.zip';
    }

    /**
     * Erstellt Info-Datei für gefilterte Belege
     */
    private function erstelleInfoDatei($belege, $filter)
    {
        $info = "==============================================\n";
        $info .= "BELEGE-EXPORT - KOMPLETTARCHIV\n";
        $info .= "==============================================\n\n";

        $info .= "Export erstellt:    " . date('d.m.Y H:i:s') . "\n";
        $info .= "Anzahl Belege:      " . count($belege) . "\n";
        $info .= "Gesamtsumme:        " . number_format(array_sum(array_column($belege, 'betrag')), 2, ',', '.') . " €\n\n";

        // Filter-Informationen
        $info .= "ANGEWENDETE FILTER:\n";
        $info .= str_repeat('-', 50) . "\n";
        if (!empty($filter['datum_von'])) {
            $info .= "Von Datum:          " . date('d.m.Y', strtotime($filter['datum_von'])) . "\n";
        }
        if (!empty($filter['datum_bis'])) {
            $info .= "Bis Datum:          " . date('d.m.Y', strtotime($filter['datum_bis'])) . "\n";
        }
        if (!empty($filter['kategorie'])) {
            $info .= "Kategorie:          " . $this->getKategorieLabel($filter['kategorie']) . "\n";
        }
        if (!empty($filter['status'])) {
            $info .= "Status:             " . $this->getStatusLabel($filter['status']) . "\n";
        }
        if (!empty($filter['suche'])) {
            $info .= "Suchbegriff:        " . $filter['suche'] . "\n";
        }
        if (empty(array_filter($filter))) {
            $info .= "Keine Filter aktiv - Alle Belege exportiert\n";
        }

        $info .= "\nBELEG-ÜBERSICHT:\n";
        $info .= str_repeat('=', 80) . "\n";
        $info .= sprintf("%-15s %-12s %-30s %-12s %s\n",
            "Belegnummer", "Datum", "Beschreibung", "Betrag", "Bezugsquelle");
        $info .= str_repeat('-', 80) . "\n";

        foreach ($belege as $beleg) {
            $beschreibung = strlen($beleg['beschreibung']) > 30 ?
                substr($beleg['beschreibung'], 0, 27) . '...' :
                $beleg['beschreibung'];

            $info .= sprintf("%-15s %-12s %-30s %10s € %s\n",
                $beleg['belegnummer'],
                date('d.m.Y', strtotime($beleg['rechnungsdatum'])),
                $beschreibung,
                number_format($beleg['betrag'], 2, ',', '.'),
                $beleg['lieferant'] ?: '-');
        }

        $info .= str_repeat('-', 80) . "\n";
        $info .= sprintf("%58s %10s €\n", "GESAMTSUMME:",
            number_format(array_sum(array_column($belege, 'betrag')), 2, ',', '.'));

        $info .= "\n\nDATEI-STRUKTUR:\n";
        $info .= str_repeat('=', 50) . "\n";
        $info .= "- Belege_Liste_[Datum].xlsx (Excel mit allen Daten)\n";
        $info .= "- 00_Export_Info.txt (diese Datei)\n";
        $info .= "- Belege/ (Ordner mit allen Beleg-Dateien)\n";
        foreach ($belege as $index => $beleg) {
            $dateiname = $this->generiereZipDateiname($beleg, $index + 1);
            $info .= "  - {$dateiname}\n";
        }

        return $info;
    }

    /**
     * Generiert aussagekräftigen Dateinamen für ZIP
     */
    private function generiereZipDateiname($beleg, $laufendeNummer)
    {
        // Format: 01_2024-06-15-001_Beschreibung.pdf
        $prefix = str_pad($laufendeNummer, 2, '0', STR_PAD_LEFT);
        $belegnummer = $beleg['belegnummer'];

        // Beschreibung für Dateiname vorbereiten (max 30 Zeichen, nur sichere Zeichen)
        $beschreibung = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', $beleg['beschreibung']);
        $beschreibung = preg_replace('/\s+/', '_', trim($beschreibung));
        $beschreibung = substr($beschreibung, 0, 30);
        $beschreibung = rtrim($beschreibung, '_');

        $extension = $beleg['dateityp'];

        if (!empty($beschreibung)) {
            return "{$prefix}_{$belegnummer}_{$beschreibung}.{$extension}";
        } else {
            return "{$prefix}_{$belegnummer}.{$extension}";
        }
    }

    /**
     * Konvertiert Kategorie zu Label
     */
    private function getKategorieLabel($kategorie)
    {
        $labels = [
            'normal' => 'Normal',
            'ah_berechtigt' => 'AH² berechtigt',
            'hv_berechtigt' => 'HV berechtigt'
        ];
        return $labels[$kategorie] ?? $kategorie;
    }

    /**
     * Konvertiert Status zu Label
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'erfasst' => 'Erfasst',
            'in_abrechnung' => 'In Abrechnung',
            'abgerechnet' => 'Abgerechnet',
            'bezahlt' => 'Bezahlt'
        ];
        return $labels[$status] ?? $status;
    }
}