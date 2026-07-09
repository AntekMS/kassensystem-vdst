<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ZipHelper - Erstellt ZIP-Archive mit Belegen + EXCEL-DATEI
 *
 * Wie ein intelligenter Archivar, der alle Dokumente ordentlich zusammenpackt
 */
class ZipHelper
{
    /**
     * Erstellt ZIP-Archiv mit allen Belegen einer Abrechnung + EXCEL-DATEI
     *
     * @param array $abrechnung Die Abrechnungs-Daten
     * @param array $belege Array von Beleg-Daten
     * @param string $typ 'ah' oder 'hv'
     * @return string|false Pfad zur ZIP-Datei oder false bei Fehler
     */
    public static function erstelleBelegeZip($abrechnung, $belege, $typ)
    {
        try {
            // Temporäres Verzeichnis für ZIP
            $tempDir = WRITEPATH . 'temp/zip/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // ZIP-Dateiname generieren
            $zipFilename = strtoupper($typ) . '_Komplett_' .
                $abrechnung['abrechnungsmonat'] . '_' .
                time() . '.zip';
            $zipPath = $tempDir . $zipFilename;

            // ZIP erstellen
            $zip = new \ZipArchive();
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if ($result !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden. Fehlercode: ' . $result);
            }

            // ===== 1. EXCEL-DATEI ERSTELLEN UND HINZUFÜGEN =====
            $excelFilename = strtoupper($typ) . '_Abrechnung_' .
                $abrechnung['abrechnungsmonat'] . '.xlsx';

            if ($typ === 'ah') {
                $spreadsheet = \App\Helpers\ExcelHelper::erstelleAhAbrechnung($abrechnung, $belege);
            } else {
                $spreadsheet = \App\Helpers\ExcelHelper::erstelleHvAbrechnung($abrechnung, $belege);
            }

            // Excel temporär speichern
            $tempExcelPath = $tempDir . 'temp_' . $excelFilename;
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempExcelPath);

            // Excel zur ZIP hinzufügen
            $zip->addFile($tempExcelPath, $excelFilename);

            // ===== 2. ALLE BELEG-DATEIEN HINZUFÜGEN =====
            $erfolgreich = 0;
            $fehlgeschlagen = 0;
            $fehlgeschlageneListe = [];

            foreach ($belege as $index => $beleg) {
                $originalDatei = FCPATH . $beleg['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen++;
                    $fehlgeschlageneListe[] = $beleg['belegnummer'];
                    continue;
                }

                // Aussagekräftigen Dateinamen generieren
                $neuerDateiname = self::generiereZipDateiname($beleg, $index + 1);

                if ($zip->addFile($originalDatei, 'Belege/' . $neuerDateiname)) {
                    $erfolgreich++;
                } else {
                    log_message('error', "Beleg konnte nicht zur ZIP hinzugefügt werden: {$beleg['belegnummer']}");
                    $fehlgeschlagen++;
                    $fehlgeschlageneListe[] = $beleg['belegnummer'];
                }
            }

            // ===== 3. INFO-DATEI HINZUFÜGEN =====
            $zip->addFromString('00_Info.txt', self::erstelleInfoDatei($abrechnung, $belege, $typ, $fehlgeschlageneListe));

            // ZIP schließen
            $zip->close();

            // Temporäre Excel-Datei löschen
            if (file_exists($tempExcelPath)) {
                unlink($tempExcelPath);
            }

            // Prüfen ob ZIP erfolgreich erstellt wurde
            if (!file_exists($zipPath)) {
                throw new \Exception('ZIP-Datei wurde nicht erstellt.');
            }

            return $zipPath;

        } catch (\Exception $e) {
            log_message('error', 'ZipHelper Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generiert aussagekräftigen Dateinamen für Beleg in ZIP
     */
    private static function generiereZipDateiname($beleg, $laufendeNummer)
    {
        // Format: 01_2024-06-15-001_Beschreibung.pdf
        $prefix = str_pad($laufendeNummer, 2, '0', STR_PAD_LEFT);
        $belegnummer = $beleg['belegnummer'];

        // Beschreibung für Dateiname vorbereiten (max 40 Zeichen, nur sichere Zeichen)
        $beschreibung = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', $beleg['beschreibung']);
        $beschreibung = preg_replace('/\s+/', '_', trim($beschreibung));
        $beschreibung = substr($beschreibung, 0, 40);
        $beschreibung = rtrim($beschreibung, '_');

        $extension = $beleg['dateityp'];

        if (!empty($beschreibung)) {
            return "{$prefix}_{$belegnummer}_{$beschreibung}.{$extension}";
        } else {
            return "{$prefix}_{$belegnummer}.{$extension}";
        }
    }

    /**
     * Erstellt eine kompakte Info-Datei für das ZIP-Archiv
     */
    private static function erstelleInfoDatei($abrechnung, $belege, $typ, array $fehlgeschlageneListe = [])
    {
        $typName = $typ === 'ah' ? 'AH²' : 'Heimverein';

        $info = "VDSt Kassensystem - {$typName} Abrechnung\n";
        $info .= str_repeat('=', 60) . "\n\n";
        $info .= sprintf("%-20s %s\n", 'Titel:', $abrechnung['titel']);
        $info .= sprintf("%-20s %s\n", 'Abrechnungsmonat:', $abrechnung['abrechnungsmonat']);
        $info .= sprintf("%-20s %s\n", 'Status:', ucfirst($abrechnung['status']));
        $info .= sprintf("%-20s %s\n", 'Export erstellt:', date('d.m.Y H:i'));
        $info .= sprintf("%-20s %d\n", 'Anzahl Belege:', count($belege));
        $info .= sprintf("%-20s %s €\n", 'Gesamtsumme:', number_format($abrechnung['gesamtsumme'], 2, ',', '.'));

        if ($typ === 'hv' && !empty($abrechnung['begruendung'])) {
            $info .= "\nBegründung:\n" . wordwrap($abrechnung['begruendung'], 58) . "\n";
        }

        $info .= "\nInhalt: Excel-Abrechnung + Ordner 'Belege/' mit allen Original-Dateien.\n";

        if (!empty($fehlgeschlageneListe)) {
            $info .= "\nACHTUNG - folgende Beleg-Dateien wurden nicht gefunden und fehlen im Archiv:\n- "
                . implode("\n- ", $fehlgeschlageneListe) . "\n";
        }

        return $info;
    }

    /**
     * Löscht alte temporäre ZIP-Dateien (Cleanup)
     */
    public static function cleanupTempZips($maxAge = 3600)
    {
        $tempDir = WRITEPATH . 'temp/zip/';
        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . '*.zip');
        $now = time();
        $geloescht = 0;

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                if (unlink($file)) {
                    $geloescht++;
                }
            }
        }

        log_message('info', "ZIP-Cleanup: {$geloescht} alte Dateien gelöscht");
    }
}