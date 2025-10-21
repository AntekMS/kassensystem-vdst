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

            // Excel mit ExcelHelper erstellen
            require_once APPPATH . 'Helpers/ExcelHelper.php';

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

            // ===== 2. INFO-DATEI ERSTELLEN =====
            $infoContent = self::erstelleInfoDatei($abrechnung, $belege, $typ);
            $zip->addFromString('00_Lesen_Zuerst.txt', $infoContent);

            // ===== 3. ALLE BELEG-DATEIEN HINZUFÜGEN =====
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

            // ===== 4. STATUS-BERICHT HINZUFÜGEN =====
            $statusBericht = self::erstelleStatusBericht($erfolgreich, $fehlgeschlagen, $fehlgeschlageneListe, $abrechnung, $typ);
            $zip->addFromString('00_Export_Status.txt', $statusBericht);

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
     * Erstellt ausführliche Info-Datei für das ZIP-Archiv
     */
    private static function erstelleInfoDatei($abrechnung, $belege, $typ)
    {
        $typName = $typ === 'ah' ? 'AH²' : 'Heimverein';

        $info = "╔════════════════════════════════════════════════════════════════╗\n";
        $info .= "║  VDSt KASSENSYSTEM - {$typName} ABRECHNUNG (KOMPLETT-ARCHIV)  ║\n";
        $info .= "╚════════════════════════════════════════════════════════════════╝\n\n";

        $info .= "ABRECHNUNGS-INFORMATION:\n";
        $info .= str_repeat('─', 70) . "\n";
        $info .= sprintf("%-20s %s\n", "Titel:", $abrechnung['titel']);
        $info .= sprintf("%-20s %s\n", "Abrechnungsmonat:", $abrechnung['abrechnungsmonat']);
        $info .= sprintf("%-20s %s\n", "Status:", ucfirst($abrechnung['status']));
        $info .= sprintf("%-20s %s\n", "Erstellt am:", date('d.m.Y H:i', strtotime($abrechnung['erstellt_am'])));
        $info .= sprintf("%-20s %s\n", "Export erstellt:", date('d.m.Y H:i:s'));
        $info .= "\n";

        $info .= "FINANZIELLE ÜBERSICHT:\n";
        $info .= str_repeat('─', 70) . "\n";
        $info .= sprintf("%-20s %d Belege\n", "Anzahl Belege:", count($belege));
        $info .= sprintf("%-20s %s €\n", "Gesamtsumme:", number_format($abrechnung['gesamtsumme'], 2, ',', '.'));
        $info .= "\n";

        if ($typ === 'hv' && !empty($abrechnung['begruendung'])) {
            $info .= "BEGRÜNDUNG FÜR HEIMVEREIN:\n";
            $info .= str_repeat('─', 70) . "\n";
            $info .= wordwrap($abrechnung['begruendung'], 68) . "\n\n";
        }

        $info .= "INHALT DIESES ARCHIVS:\n";
        $info .= str_repeat('═', 70) . "\n";
        $info .= "📊 " . strtoupper($typ) . "_Abrechnung_{$abrechnung['abrechnungsmonat']}.xlsx\n";
        $info .= "   → Excel-Tabelle mit allen Beleg-Details und Gesamtsumme\n";
        $info .= "   → Kann direkt zur Einreichung verwendet werden\n\n";
        $info .= "📁 Belege/ (Ordner mit allen Original-Dateien)\n";
        $info .= "   → " . count($belege) . " nummerierte Beleg-Dateien\n";
        $info .= "   → Aussagekräftige Dateinamen (Nummer_Belegnr_Beschreibung)\n\n";
        $info .= "📄 00_Lesen_Zuerst.txt (diese Datei)\n";
        $info .= "   → Informationen über den Inhalt des Archivs\n\n";
        $info .= "📄 00_Export_Status.txt\n";
        $info .= "   → Technischer Status-Bericht des Exports\n\n";

        $info .= "\nBELEG-LISTE (DETAILLIERT):\n";
        $info .= str_repeat('═', 80) . "\n";
        $info .= sprintf("%-4s %-15s %-12s %-30s %12s  %-15s\n",
            "Nr.", "Belegnummer", "Datum", "Beschreibung", "Betrag", "Bezugsquelle");
        $info .= str_repeat('─', 80) . "\n";

        foreach ($belege as $index => $beleg) {
            $nr = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            $beschreibung = strlen($beleg['beschreibung']) > 30 ?
                substr($beleg['beschreibung'], 0, 27) . '...' :
                $beleg['beschreibung'];

            $info .= sprintf("%-4s %-15s %-12s %-30s %10s €  %-15s\n",
                $nr,
                $beleg['belegnummer'],
                date('d.m.Y', strtotime($beleg['rechnungsdatum'])),
                $beschreibung,
                number_format($beleg['betrag'], 2, ',', '.'),
                $beleg['lieferant'] ?: 'Kassenwart');
        }

        $info .= str_repeat('─', 80) . "\n";
        $info .= sprintf("%62s %10s €\n", "GESAMTSUMME:",
            number_format($abrechnung['gesamtsumme'], 2, ',', '.'));

        $info .= "\n\nVERWENDUNG:\n";
        $info .= str_repeat('═', 70) . "\n";
        $info .= "1. Öffnen Sie die Excel-Datei zur Übersicht\n";
        $info .= "2. Im Ordner 'Belege/' finden Sie alle Original-Dateien\n";
        $info .= "3. Die Dateinamen sind durchnummeriert und beschreibend\n";
        $info .= "4. Einreichung: Excel + Belege-Ordner komplett verwenden\n\n";

        return $info;
    }

    /**
     * Erstellt Status-Bericht für den Export
     */
    private static function erstelleStatusBericht($erfolgreich, $fehlgeschlagen, $fehlgeschlageneListe, $abrechnung, $typ)
    {
        $bericht = "╔══════════════════════════════════════════════════════════╗\n";
        $bericht .= "║           EXPORT-STATUS-BERICHT                          ║\n";
        $bericht .= "╚══════════════════════════════════════════════════════════╝\n\n";

        $bericht .= "Export-Details:\n";
        $bericht .= str_repeat('─', 60) . "\n";
        $bericht .= sprintf("%-25s %s\n", "Abrechnung:", $abrechnung['titel']);
        $bericht .= sprintf("%-25s %s\n", "Typ:", strtoupper($typ));
        $bericht .= sprintf("%-25s %s\n", "Export-Zeitpunkt:", date('d.m.Y H:i:s'));
        $bericht .= sprintf("%-25s %s\n", "System-Version:", '1.0.8');
        $bericht .= "\n";

        $bericht .= "Export-Statistik:\n";
        $bericht .= str_repeat('─', 60) . "\n";
        $bericht .= sprintf("%-25s %d\n", "✅ Excel-Datei:", 1);
        $bericht .= sprintf("%-25s %d\n", "✅ Belege erfolgreich:", $erfolgreich);
        $bericht .= sprintf("%-25s %d\n", "❌ Belege fehlgeschlagen:", $fehlgeschlagen);
        $bericht .= sprintf("%-25s %d\n", "📊 Gesamt-Dateien:", $erfolgreich + 1);
        $bericht .= "\n";

        if ($fehlgeschlagen > 0) {
            $bericht .= "⚠️  FEHLGESCHLAGENE BELEGE:\n";
            $bericht .= str_repeat('─', 60) . "\n";
            foreach ($fehlgeschlageneListe as $belegnr) {
                $bericht .= "   - Belegnummer: {$belegnr}\n";
            }
            $bericht .= "\nHinweis: Diese Belege wurden übersprungen, da die Original-\n";
            $bericht .= "Dateien nicht gefunden wurden. Die Excel-Liste enthält aber\n";
            $bericht .= "trotzdem alle Beleg-Daten zur Übersicht.\n\n";
        } else {
            $bericht .= "✅ VOLLSTÄNDIGER EXPORT\n";
            $bericht .= str_repeat('─', 60) . "\n";
            $bericht .= "Alle Belege wurden erfolgreich exportiert!\n";
            $bericht .= "Excel-Datei + alle " . $erfolgreich . " Beleg-Dateien sind enthalten.\n\n";
        }

        $bericht .= "Inhalt des Archivs:\n";
        $bericht .= str_repeat('─', 60) . "\n";
        $bericht .= "✅ " . strtoupper($typ) . "_Abrechnung_{$abrechnung['abrechnungsmonat']}.xlsx\n";
        $bericht .= "✅ 00_Lesen_Zuerst.txt (Übersicht)\n";
        $bericht .= "✅ 00_Export_Status.txt (diese Datei)\n";
        $bericht .= "✅ Belege/ (Ordner mit {$erfolgreich} Dateien)\n\n";

        $bericht .= "System-Information:\n";
        $bericht .= str_repeat('─', 60) . "\n";
        $bericht .= "VDSt Kassensystem - Version 1.0.8\n";
        $bericht .= "Digitales Kassenbuch und Abrechnungssystem\n";
        $bericht .= "Verein deutscher Studenten\n\n";

        return $bericht;
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