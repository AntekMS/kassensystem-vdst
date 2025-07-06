<?php

namespace App\Helpers;

use ZipArchive;

/**
 * ZipHelper - Intelligenter ZIP-Archiv Manager
 *
 * Wie ein digitaler Aktenkopierer:
 * - Sammelt alle Belege einer Abrechnung
 * - Erstellt strukturierte ZIP-Archive
 * - Benennt Dateien aussagekräftig um
 * - Fügt Zusatzinformationen hinzu
 */
class ZipHelper
{
    /**
     * Erstellt ZIP-Archiv mit allen Belegen einer Abrechnung
     *
     * @param array $abrechnung Abrechnungs-Daten
     * @param array $belege Array der Belege
     * @param string $typ 'ah' oder 'hv'
     * @return string|false Pfad zur ZIP-Datei oder false bei Fehler
     */
    public static function erstelleBelegeZip($abrechnung, $belege, $typ)
    {
        if (empty($belege)) {
            throw new \Exception('Keine Belege zum Archivieren gefunden.');
        }

        // Temporäres Verzeichnis für ZIP
        $tempDir = WRITEPATH . 'temp/zip/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFilename = 'belege_' . $typ . '_' . $abrechnung['id'] . '_' . time() . '.zip';
        $zipPath = $tempDir . $zipFilename;

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \Exception('ZIP-Archiv konnte nicht erstellt werden. Fehlercode: ' . $result);
        }

        try {
            // Abrechnung-Info-Datei erstellen
            $infoContent = self::erstelleInfoDatei($abrechnung, $belege, $typ);
            $zip->addFromString('00_Abrechnung_Info.txt', $infoContent);

            $erfolgreich = 0;
            $fehlgeschlagen = 0;

            // Belege hinzufügen
            foreach ($belege as $index => $beleg) {
                $originalDatei = FCPATH . $beleg['dateipfad'];

                if (!file_exists($originalDatei)) {
                    log_message('warning', "Beleg-Datei nicht gefunden: {$originalDatei}");
                    $fehlgeschlagen++;
                    continue;
                }

                // Aussagekräftigen Dateinamen generieren
                $neuerDateiname = self::generiereZipDateiname($beleg, $index + 1);

                if ($zip->addFile($originalDatei, $neuerDateiname)) {
                    $erfolgreich++;
                } else {
                    log_message('error', "Beleg konnte nicht zur ZIP hinzugefügt werden: {$beleg['belegnummer']}");
                    $fehlgeschlagen++;
                }
            }

            // Zusammenfassung
            if ($fehlgeschlagen > 0) {
                $fehlerInfo = "\n\n=== HINWEISE ===\n";
                $fehlerInfo .= "Erfolgreich archiviert: {$erfolgreich} Belege\n";
                $fehlerInfo .= "Fehlgeschlagen: {$fehlgeschlagen} Belege\n";
                $fehlerInfo .= "Fehlgeschlagene Belege wurden übersprungen.\n";

                $zip->addFromString('00_Archivierung_Hinweise.txt', $fehlerInfo);
            }

            $zip->close();

            if ($erfolgreich === 0) {
                throw new \Exception('Keine Belege konnten archiviert werden.');
            }

            return $zipPath;

        } catch (\Exception $e) {
            $zip->close();
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            throw $e;
        }
    }

    /**
     * Erstellt Info-Datei mit Abrechnungsdetails
     */
    private static function erstelleInfoDatei($abrechnung, $belege, $typ)
    {
        $info = "==============================================\n";
        $info .= strtoupper($typ) . " ABRECHNUNG - BELEG-ARCHIV\n";
        $info .= "==============================================\n\n";

        $info .= "Abrechnung:     " . $abrechnung['titel'] . "\n";
        $info .= "Monat:          " . $abrechnung['abrechnungsmonat'] . "\n";
        $info .= "Status:         " . ucfirst($abrechnung['status']) . "\n";
        $info .= "Erstellt am:    " . date('d.m.Y', strtotime($abrechnung['erstellt_am'])) . "\n";
        $info .= "Anzahl Belege:  " . count($belege) . "\n";
        $info .= "Gesamtsumme:    " . number_format($abrechnung['gesamtsumme'], 2, ',', '.') . " €\n";
        $info .= "Archiv erstellt: " . date('d.m.Y H:i:s') . "\n\n";

        if ($typ === 'hv' && !empty($abrechnung['begruendung'])) {
            $info .= "HV-Begründung:\n";
            $info .= str_repeat('-', 50) . "\n";
            $info .= $abrechnung['begruendung'] . "\n\n";
        }

        $info .= "BELEG-ÜBERSICHT:\n";
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
            number_format($abrechnung['gesamtsumme'], 2, ',', '.'));

        $info .= "\n\nDATEI-STRUKTUR:\n";
        $info .= str_repeat('=', 50) . "\n";
        $info .= "- 00_Abrechnung_Info.txt (diese Datei)\n";
        foreach ($belege as $index => $beleg) {
            $dateiname = self::generiereZipDateiname($beleg, $index + 1);
            $info .= "- {$dateiname}\n";
        }

        return $info;
    }

    /**
     * Generiert aussagekräftigen Dateinamen für ZIP
     */
    private static function generiereZipDateiname($beleg, $laufendeNummer)
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
     * Löscht alte temporäre ZIP-Dateien (Cleanup)
     */
    public static function cleanupTempZips($maxAge = 3600)
    {
        $tempDir = WRITEPATH . 'temp/zip/';
        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . 'belege_*.zip');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
}