<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * ExcelHelper - Erstellt Excel-Dateien im Format deines Kassenbuchs
 */
class ExcelHelper
{
    /**
     * Erstellt Kassenbuch-Excel genau wie dein Format
     */
    public static function erstelleKassenbuch($buchungen, $kontostaende, $filter = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kassenbuch');

        // ===== HEADER STRUKTUR WIE DEIN KASSENBUCH =====

        // Zeile 1: Hauptheader
        $sheet->setCellValue('B1', 'Datum');
        $sheet->setCellValue('C1', 'Beschreibung');
        $sheet->setCellValue('D1', 'Beleg Nr.');
        $sheet->setCellValue('E1', 'Aktivenkasse');
        $sheet->setCellValue('G1', 'Getränkekasse');
        $sheet->setCellValue('I1', 'Barkasse');
        $sheet->setCellValue('K1', 'Gesamt:');

        // Gesamtsaldo berechnen
        $gesamtsaldo = 0;
        foreach ($kontostaende as $saldo) {
            $gesamtsaldo += $saldo['saldo'];
        }
        $sheet->setCellValue('L1', $gesamtsaldo);

        // Zeile 2: Ein/Aus Subheader
        $sheet->setCellValue('E2', 'Ein');
        $sheet->setCellValue('F2', 'Aus');
        $sheet->setCellValue('G2', 'Ein');
        $sheet->setCellValue('H2', 'Aus');
        $sheet->setCellValue('I2', 'Ein');
        $sheet->setCellValue('J2', 'Aus');

        // Zeile 3: Kontostände
        $sheet->setCellValue('C3', 'Stand');
        $sheet->setCellValue('E3', $kontostaende['aktivenkasse']['saldo'] ?? 0);
        $sheet->setCellValue('G3', $kontostaende['getraenkekasse']['saldo'] ?? 0);
        $sheet->setCellValue('I3', $kontostaende['barkasse']['saldo'] ?? 0);

        // ===== BUCHUNGEN EINTRAGEN =====

        $zeile = 4; // Start bei Zeile 4 wie in deinem Excel

        foreach ($buchungen as $buchung) {
            // Datum formatieren wie in Excel
            $datum = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($buchung['buchungsdatum']));
            $sheet->setCellValue('B' . $zeile, $datum);

            // Beschreibung
            $sheet->setCellValue('C' . $zeile, $buchung['beschreibung']);

            // Belegnummer falls vorhanden
            if (!empty($buchung['belegnummer'])) {
                $sheet->setCellValue('D' . $zeile, $buchung['belegnummer']);
            }

            // Betrag in richtige Spalte je nach Konto und Art
            $betrag = $buchung['betrag'];
            $istEinnahme = $buchung['buchungsart'] === 'einnahme';

            switch ($buchung['konto_typ']) {
                case 'aktivenkasse':
                    $spalte = $istEinnahme ? 'E' : 'F';
                    $sheet->setCellValue($spalte . $zeile, $betrag);
                    break;
                case 'getraenkekasse':
                    $spalte = $istEinnahme ? 'G' : 'H';
                    $sheet->setCellValue($spalte . $zeile, $betrag);
                    break;
                case 'barkasse':
                    $spalte = $istEinnahme ? 'I' : 'J';
                    $sheet->setCellValue($spalte . $zeile, $betrag);
                    break;
            }

            $zeile++;
        }

        // ===== FORMATIERUNG WIE DEIN EXCEL =====

        // Spaltenbreiten anpassen
        $sheet->getColumnDimension('B')->setWidth(12); // Datum
        $sheet->getColumnDimension('C')->setWidth(40); // Beschreibung
        $sheet->getColumnDimension('D')->setWidth(15); // Beleg Nr.
        $sheet->getColumnDimension('E')->setWidth(12); // Aktivenkasse Ein
        $sheet->getColumnDimension('F')->setWidth(12); // Aktivenkasse Aus
        $sheet->getColumnDimension('G')->setWidth(12); // Getränkekasse Ein
        $sheet->getColumnDimension('H')->setWidth(12); // Getränkekasse Aus
        $sheet->getColumnDimension('I')->setWidth(12); // Barkasse Ein
        $sheet->getColumnDimension('J')->setWidth(12); // Barkasse Aus
        $sheet->getColumnDimension('K')->setWidth(12); // Gesamt Label
        $sheet->getColumnDimension('L')->setWidth(15); // Gesamt Wert

        // Header-Formatierung
        $headerRange = 'B1:L3';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Datum-Formatierung
        $sheet->getStyle('B4:B' . ($zeile - 1))->getNumberFormat()->setFormatCode('DD.MM.YYYY');

        // Geld-Formatierung für alle Beträge
        $geldSpalten = ['E', 'F', 'G', 'H', 'I', 'J', 'L'];
        foreach ($geldSpalten as $spalte) {
            $sheet->getStyle($spalte . '1:' . $spalte . ($zeile - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0.00 "€";[Red]-#,##0.00 "€"');
        }

        // Rahmen um den ganzen Bereich
        $sheet->getStyle('B1:L' . ($zeile - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $spreadsheet;
    }

    /**
     * Erstellt das Abrechnungs-Excel für AH² oder HV.
     *
     * Der Typ steuert nur Sheet-/Default-Titel, Header-Farbe und — bei HV — den
     * Freitext-Begründungs-Block, der die Tabelle um eine Zeile nach unten schiebt.
     *
     * @param array  $abrechnung
     * @param array  $belege
     * @param string $typ 'ah' | 'hv'
     * @return Spreadsheet
     */
    public static function erstelleAbrechnung($abrechnung, $belege, string $typ)
    {
        $istHv = $typ === 'hv';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($istHv ? 'HV Abrechnung' : 'AH² Abrechnung');

        // Titel der Abrechnung
        $sheet->setCellValue('A1', $abrechnung['titel'] ?? ($istHv ? 'Heimverein Abrechnung' : 'Abrechnung Alt-Herren-Bund'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:E1');

        // Begründung (Freitext, nur HV) falls vorhanden — schiebt den Header eine Zeile runter
        $headerZeile = 2;

        if ($istHv && !empty($abrechnung['begruendung'])) {
            $sheet->setCellValue('A2', 'Begründung:');
            $sheet->setCellValue('B2', $abrechnung['begruendung']);
            $sheet->getStyle('A2')->getFont()->setBold(true);
            $sheet->mergeCells('B2:E2');
            $headerZeile = 3;
        }

        // Header: Beschreibung | Datum | Beleg | Betrag | Bezugsquelle
        $sheet->setCellValue('A' . $headerZeile, 'Beschreibung');
        $sheet->setCellValue('B' . $headerZeile, 'Datum');
        $sheet->setCellValue('C' . $headerZeile, 'Beleg');
        $sheet->setCellValue('D' . $headerZeile, 'Betrag');
        $sheet->setCellValue('E' . $headerZeile, 'Bezugsquelle');

        // Header-Formatierung (Hellgelb für HV, Grau für AH²)
        $headerBereich = 'A' . $headerZeile . ':E' . $headerZeile;
        $sheet->getStyle($headerBereich)->getFont()->setBold(true);
        $sheet->getStyle($headerBereich)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerBereich)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($istHv ? 'FFE4B5' : 'DDDDDD');

        // Belege eintragen
        $ersteBelegZeile = $headerZeile + 1;
        $zeile = $ersteBelegZeile;

        foreach ($belege as $beleg) {
            $sheet->setCellValue('A' . $zeile, $beleg['beschreibung']);
            $sheet->setCellValue('B' . $zeile,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                    strtotime($beleg['rechnungsdatum'])
                )
            );
            $sheet->setCellValue('C' . $zeile, $beleg['belegnummer'] ?? '');
            $sheet->setCellValue('D' . $zeile, $beleg['betrag']);
            $sheet->setCellValue('E' . $zeile, $beleg['lieferant'] ?: 'Kassenwart');
            $zeile++;
        }

        // Gesamtsumme
        $sheet->setCellValue('C' . $zeile, 'GESAMTSUMME:');
        $sheet->setCellValue('D' . $zeile, $abrechnung['gesamtsumme']);
        $sheet->getStyle('C' . $zeile . ':D' . $zeile)->getFont()->setBold(true);

        // Spaltenbreiten
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(25);

        // Datum formatieren
        $sheet->getStyle('B' . $ersteBelegZeile . ':B' . ($zeile - 1))
            ->getNumberFormat()
            ->setFormatCode('DD.MM.YYYY');

        // Beträge formatieren
        $sheet->getStyle('D' . $ersteBelegZeile . ':D' . $zeile)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00 "€"');

        // Rahmen
        $sheet->getStyle('A' . $headerZeile . ':E' . $zeile)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        return $spreadsheet;
    }

    /**
     * Erstellt Inventur-Excel "Kassenwart – Aktueller Bestand"
     *
     * Summe Gesamt = Kassenbestand (I) + Forderungen (II) − Verbindlichkeiten (III)
     *
     * @param array $kontostaende aus BuchungModel::berechneKontostaende()
     * @param array $inventur     aus SchuldModel::berechneInventur()
     */
    public static function erstelleInventur($kontostaende, $inventur)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventur');

        $geldFormat = '#,##0.00 "€";[Red]-#,##0.00 "€"';

        // Titel
        $sheet->setCellValue('A1', 'Kassenwart – Aktueller Bestand');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A2', 'Stand: ' . date('d.m.Y'));

        $zeile = 4;
        $blockStart = static function ($sheet, $zeile, $titel) {
            $sheet->setCellValue('A' . $zeile, $titel);
            $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFont()->setBold(true);
            $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');
        };

        // ===== I. Kassenbestand =====
        $blockStart($sheet, $zeile, 'I. Kassenbestand');
        $zeile++;

        $summeKassen = 0;
        foreach ($kontostaende as $konto => $daten) {
            $sheet->setCellValue('A' . $zeile, konto_label($konto));
            $sheet->setCellValue('B' . $zeile, $daten['saldo']);
            $summeKassen += $daten['saldo'];
            $zeile++;
        }

        $sheet->setCellValue('A' . $zeile, 'Summe I');
        $sheet->setCellValue('B' . $zeile, $summeKassen);
        $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFont()->setBold(true);
        $zeile += 2;

        // ===== II. Forderungen / III. Verbindlichkeiten =====
        $bloecke = [
            'forderung' => ['titel' => 'II. Forderungen', 'summe_label' => 'Summe II'],
            'verbindlichkeit' => ['titel' => 'III. Verbindlichkeiten', 'summe_label' => 'Summe III'],
        ];

        foreach ($bloecke as $typ => $block) {
            $blockStart($sheet, $zeile, $block['titel']);
            $zeile++;

            foreach (schuld_kategorie_optionen() as $kategorie => $label) {
                $sheet->setCellValue('A' . $zeile, $label);
                $sheet->setCellValue('B' . $zeile, $inventur[$typ][$kategorie] ?? 0);
                $zeile++;
            }

            $sheet->setCellValue('A' . $zeile, $block['summe_label']);
            $sheet->setCellValue('B' . $zeile, $inventur[$typ]['summe'] ?? 0);
            $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFont()->setBold(true);
            $zeile += 2;
        }

        // ===== Summe Gesamt =====
        $summeGesamt = $summeKassen
            + ($inventur['forderung']['summe'] ?? 0)
            - ($inventur['verbindlichkeit']['summe'] ?? 0);

        $sheet->setCellValue('A' . $zeile, 'Summe Gesamt (I + II − III)');
        $sheet->setCellValue('B' . $zeile, $summeGesamt);
        $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFont()->setBold(true);
        $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('BBBBBB');

        // Formatierung (Doppel-Rahmen NACH getAllBorders, sonst wird er überschrieben)
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getStyle('B4:B' . $zeile)->getNumberFormat()->setFormatCode($geldFormat);
        $sheet->getStyle('A4:B' . $zeile)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $zeile . ':B' . $zeile)->getBorders()
            ->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        return $spreadsheet;
    }

    /**
     * Speichert Excel und gibt Download-Response zurück
     */
    public static function downloadExcel($spreadsheet, $filename)
    {
        $writer = new Xlsx($spreadsheet);

        // In temporäre Datei schreiben — mögliche Fehler passieren hier, VOR jeder
        // Ausgabe, sodass der Aufrufer sie per try/catch sauber abfangen kann
        // (statt rohem header()/exit, das den Response-Zyklus umgeht).
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');

        try {
            $writer->save($tempFile);
            $inhalt = file_get_contents($tempFile);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        return service('response')
            ->download($filename, $inhalt)
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}