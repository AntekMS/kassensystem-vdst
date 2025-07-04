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
     * Erstellt AH²-Abrechnung Excel wie dein Format
     */
    public static function erstelleAhAbrechnung($abrechnung, $belege)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('AH² Abrechnung');

        // Header wie in deinem AH-Sheet
        $sheet->setCellValue('A2', 'Name:');
        $sheet->setCellValue('B2', 'Datum');
        $sheet->setCellValue('C2', 'Grund:');
        $sheet->setCellValue('D2', 'Betrag:');
        $sheet->setCellValue('E2', 'Status:');
        $sheet->setCellValue('G2', 'Done:');
        $sheet->setCellValue('H2', 'Datum');
        $sheet->setCellValue('I2', 'Beleg:');
        $sheet->setCellValue('J2', 'Summe');
        $sheet->setCellValue('L2', 'Gesamt:');
        $sheet->setCellValue('M2', $abrechnung['gesamtsumme']);

        // Belege eintragen
        $zeile = 3;
        foreach ($belege as $beleg) {
            $sheet->setCellValue('G' . $zeile, $beleg['lieferant'] ?: 'Kassenwart');
            $sheet->setCellValue('H' . $zeile, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($beleg['rechnungsdatum'])));
            $sheet->setCellValue('I' . $zeile, $beleg['beschreibung']);
            $sheet->setCellValue('J' . $zeile, $beleg['betrag']);
            $zeile++;
        }

        // Formatierung
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('I')->setWidth(40);
        $sheet->getStyle('H3:H' . ($zeile - 1))->getNumberFormat()->setFormatCode('DD.MM.YYYY');
        $sheet->getStyle('J2:J' . ($zeile - 1))->getNumberFormat()->setFormatCode('#,##0.00 "€"');
        $sheet->getStyle('M2')->getNumberFormat()->setFormatCode('#,##0.00 "€"');

        return $spreadsheet;
    }

    /**
     * Erstellt HV-Abrechnung Excel
     */
    public static function erstelleHvAbrechnung($abrechnung, $belege)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('HV Abrechnung');

        // Ähnlich wie AH² aber mit Begründungen
        $sheet->setCellValue('A1', 'HV Abrechnung: ' . $abrechnung['titel']);
        $sheet->setCellValue('A2', 'Monat: ' . $abrechnung['abrechnungsmonat']);

        if (!empty($abrechnung['begruendung'])) {
            $sheet->setCellValue('A4', 'Allgemeine Begründung:');
            $sheet->setCellValue('A5', $abrechnung['begruendung']);
        }

        // Header für Belege
        $startZeile = 7;
        $sheet->setCellValue('A' . $startZeile, 'Belegnummer');
        $sheet->setCellValue('B' . $startZeile, 'Datum');
        $sheet->setCellValue('C' . $startZeile, 'Beschreibung');
        $sheet->setCellValue('D' . $startZeile, 'Lieferant');
        $sheet->setCellValue('E' . $startZeile, 'Betrag');
        $sheet->setCellValue('F' . $startZeile, 'Begründung');

        // Belege mit automatischen Begründungen
        $zeile = $startZeile + 1;
        foreach ($belege as $beleg) {
            $sheet->setCellValue('A' . $zeile, $beleg['belegnummer']);
            $sheet->setCellValue('B' . $zeile, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($beleg['rechnungsdatum'])));
            $sheet->setCellValue('C' . $zeile, $beleg['beschreibung']);
            $sheet->setCellValue('D' . $zeile, $beleg['lieferant'] ?: '-');
            $sheet->setCellValue('E' . $zeile, $beleg['betrag']);

            // Automatische Begründung generieren
            $begruendung = self::generiereHvBegruendung($beleg['beschreibung']);
            $sheet->setCellValue('F' . $zeile, $begruendung);

            $zeile++;
        }

        // Gesamtsumme
        $sheet->setCellValue('D' . $zeile, 'Gesamtsumme:');
        $sheet->setCellValue('E' . $zeile, $abrechnung['gesamtsumme']);

        // Formatierung
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(30);

        $sheet->getStyle('B' . ($startZeile + 1) . ':B' . ($zeile - 1))->getNumberFormat()->setFormatCode('DD.MM.YYYY');
        $sheet->getStyle('E' . ($startZeile + 1) . ':E' . $zeile)->getNumberFormat()->setFormatCode('#,##0.00 "€"');

        return $spreadsheet;
    }

    /**
     * Speichert Excel und gibt Download-Response zurück
     */
    public static function downloadExcel($spreadsheet, $filename)
    {
        $writer = new Xlsx($spreadsheet);

        // Headers für Download setzen
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // Direkt an Browser ausgeben
        $writer->save('php://output');
        exit;
    }

    /**
     * Generiert HV-Begründung basierend auf Beschreibung
     */
    private static function generiereHvBegruendung($beschreibung)
    {
        $beschreibung = strtolower($beschreibung);

        $begruendungen = [
            'farbe' => 'Renovierung und Instandhaltung der Hausräume',
            'pinsel' => 'Renovierung und Instandhaltung der Hausräume',
            'streichen' => 'Renovierung und Instandhaltung der Hausräume',
            'regal' => 'Möblierung und Ausstattung der Gemeinschaftsräume',
            'schrank' => 'Möblierung und Ausstattung der Gemeinschaftsräume',
            'möbel' => 'Möblierung und Ausstattung der Gemeinschaftsräume',
            'lampe' => 'Beleuchtung und elektrische Ausstattung',
            'glühbirne' => 'Beleuchtung und elektrische Ausstattung',
            'reinigung' => 'Reinigung und Hygiene der Hausräume',
            'putz' => 'Reinigung und Hygiene der Hausräume',
            'werkzeug' => 'Wartung und Reparatur der Hausausstattung',
            'schrauben' => 'Wartung und Reparatur der Hausausstattung',
            'reparatur' => 'Wartung und Reparatur der Hausausstattung',
            'küche' => 'Küchenausstattung und -wartung',
            'geschirr' => 'Küchenausstattung und -wartung'
        ];

        foreach ($begruendungen as $schluesselwort => $begruendung) {
            if (strpos($beschreibung, $schluesselwort) !== false) {
                return $begruendung;
            }
        }

        return 'Notwendige Ausgabe für das Vereinshaus';
    }
}