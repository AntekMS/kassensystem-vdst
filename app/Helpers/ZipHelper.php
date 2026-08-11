<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ZipHelper - Erstellt ZIP-Archive mit Belegen + EXCEL-DATEI
 *
 * Wie ein intelligenter Archivar, der alle Dokumente ordentlich zusammenpackt.
 *
 * `erstelleArchiv()` ist seit Issue #91 der EINZIGE Codepfad, der ein
 * Export-ZIP zusammenbaut (Abrechnungen, Belege-Liste, Kassenbuch) — die
 * Aufrufer liefern nur ihr Spreadsheet, ihre Dateiliste und ihre Abschlussdatei.
 */
class ZipHelper
{
    /**
     * Temp-Verzeichnis für Exporte (wird bei Bedarf angelegt).
     */
    public static function tempDir(): string
    {
        $tempDir = WRITEPATH . 'temp/zip/';

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return $tempDir;
    }

    /**
     * Baut ein Export-ZIP: Excel + Dateiliste + Abschlussdatei.
     *
     * Gemeinsamer Kern der drei früheren Kopien (Issue #91). Die Excel wird
     * über eine Temp-Datei geschrieben (PhpSpreadsheet kann nicht in den
     * ZipArchive-Stream) und danach wieder gelöscht.
     *
     * @param string      $zipPfad    Zielpfad des Archivs
     * @param Spreadsheet $excel      fertiges Spreadsheet des Aufrufers
     * @param string      $excelName  Name der Excel IM Archiv
     * @param array       $dateien    Liste ['pfad' => absolut, 'name' => Name im Archiv, 'label' => für Fehlerliste]
     * @param callable    $abschluss  fn(array $fehlgeschlagen): ?array{0:string,1:string} — [Dateiname, Inhalt] oder null
     * @return string Pfad zum fertigen Archiv
     * @throws \Exception wenn das Archiv nicht erstellt werden kann
     */
    public static function erstelleArchiv(
        string $zipPfad,
        Spreadsheet $excel,
        string $excelName,
        array $dateien,
        callable $abschluss
    ): string {
        $zip = new \ZipArchive();
        $result = $zip->open($zipPfad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \Exception('ZIP-Archiv konnte nicht erstellt werden. Fehlercode: ' . $result);
        }

        // 1. Excel über eine Temp-Datei hinzufügen
        $tempExcelPfad = self::tempDir() . uniqid('temp_', true) . '.xlsx';
        (new Xlsx($excel))->save($tempExcelPfad);
        $zip->addFile($tempExcelPfad, $excelName);

        // 2. Dateien hinzufügen, fehlende sammeln statt abzubrechen
        $fehlgeschlagen = [];

        foreach ($dateien as $datei) {
            if (!file_exists($datei['pfad'])) {
                log_message('warning', "Beleg-Datei nicht gefunden: {$datei['pfad']}");
                $fehlgeschlagen[] = $datei['label'];
                continue;
            }

            if (!$zip->addFile($datei['pfad'], $datei['name'])) {
                log_message('error', "Beleg konnte nicht zur ZIP hinzugefügt werden: {$datei['label']}");
                $fehlgeschlagen[] = $datei['label'];
            }
        }

        // 3. Abschlussdatei (Info bzw. Hinweise) — der Aufrufer entscheidet, ob es eine gibt
        $zusatz = $abschluss($fehlgeschlagen);

        if ($zusatz !== null) {
            $zip->addFromString($zusatz[0], $zusatz[1]);
        }

        $zip->close();

        if (file_exists($tempExcelPfad)) {
            unlink($tempExcelPfad);
        }

        if (!file_exists($zipPfad)) {
            throw new \Exception('ZIP-Datei wurde nicht erstellt.');
        }

        return $zipPfad;
    }

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
            // uniqid statt time() — verhindert Kollisionen bei parallelen
            // Exports innerhalb derselben Sekunde
            $zipPfad = self::tempDir() . strtoupper($typ) . '_Komplett_' .
                $abrechnung['abrechnungsmonat'] . '_' .
                uniqid('', true) . '.zip';

            return self::erstelleArchiv(
                $zipPfad,
                \App\Helpers\ExcelHelper::erstelleAbrechnung($abrechnung, $belege, $typ),
                strtoupper($typ) . '_Abrechnung_' . $abrechnung['abrechnungsmonat'] . '.xlsx',
                self::belegDateien($belege),
                static fn (array $fehlgeschlagen) => [
                    '00_Info.txt',
                    self::erstelleInfoDatei($abrechnung, $belege, $typ, $fehlgeschlagen),
                ]
            );
        } catch (\Exception $e) {
            log_message('error', 'ZipHelper Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Beleg-Zeilen → Dateiliste für erstelleArchiv().
     *
     * @param array $belege Zeilen mit dateipfad/belegnummer/beschreibung/dateityp
     * @param int   $maxLen Längenlimit der Beschreibung im Dateinamen
     */
    public static function belegDateien(array $belege, int $maxLen = 40): array
    {
        $dateien = [];

        foreach (array_values($belege) as $index => $beleg) {
            $dateien[] = [
                'pfad' => FCPATH . $beleg['dateipfad'],
                'name' => 'Belege/' . self::belegDateiname($beleg, $index + 1, $maxLen),
                'label' => $beleg['belegnummer'],
            ];
        }

        return $dateien;
    }

    /**
     * Generiert aussagekräftigen Dateinamen für Beleg in ZIP
     * Format: 01_2024-06-15-001_Beschreibung.pdf
     */
    public static function belegDateiname(array $beleg, int $laufendeNummer, int $maxLen = 40): string
    {
        $prefix = str_pad((string) $laufendeNummer, 2, '0', STR_PAD_LEFT);
        $beschreibung = self::dateinameTeil($beleg['beschreibung'] ?? '', $maxLen);

        $dateiname = "{$prefix}_{$beleg['belegnummer']}";

        if ($beschreibung !== '') {
            $dateiname .= "_{$beschreibung}";
        }

        return $dateiname . '.' . $beleg['dateityp'];
    }

    /**
     * Macht Freitext dateinamen-sicher: nur Buchstaben/Ziffern/Umlaute, Leerraum
     * zu Unterstrichen, auf $maxLen gekürzt, ohne Rand-Unterstrich.
     *
     * EINZIGE Sanitize-Regel für ZIP-Dateinamen (Issue #91) — die drei früheren
     * Kopien unterschieden sich nur im Längenlimit.
     */
    public static function dateinameTeil(?string $text, int $maxLen): string
    {
        $sauber = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß\s]/', '', (string) $text);
        $sauber = preg_replace('/\s+/', '_', trim($sauber));

        return rtrim(substr($sauber, 0, $maxLen), '_');
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