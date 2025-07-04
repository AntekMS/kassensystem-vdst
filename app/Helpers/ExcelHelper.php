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

        // Titel der Abrechnung
        $sheet->setCellValue('A1', $abrechnung['titel'] ?? 'Abrechnung Alt-Herren-Bund');

        // Header gemäß Excel-Vorlage: Beschreibung | Datum | Beleg | Betrag | Bezugsquelle
        $sheet->setCellValue('A2', 'Beschreibung');
        $sheet->setCellValue('B2', 'Datum');
        $sheet->setCellValue('C2', 'Beleg');
        $sheet->setCellValue('D2', 'Betrag');
        $sheet->setCellValue('E2', 'Bezugsquelle');

        // Belege eintragen – ab Zeile 3
        $zeile = 3;
        foreach ($belege as $beleg) {
            // A: Beschreibung
            $sheet->setCellValue('A' . $zeile, $beleg['beschreibung']);
            // B: Datum (Excel-Datum)
            $sheet->setCellValue('B' . $zeile,
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(
                    strtotime($beleg['rechnungsdatum'])
                )
            );
            // C: Belegnummer (wenn vorhanden)
            $sheet->setCellValue('C' . $zeile, $beleg['belegnummer'] ?? '');
            // D: Betrag
            $sheet->setCellValue('D' . $zeile, $beleg['betrag']);
            // E: Bezugsquelle (Lieferant oder Kassenwart)
            $sheet->setCellValue('E' . $zeile, $beleg['lieferant'] ?: 'Kassenwart');
            $zeile++;
        }

        // Formatierung
        // Spaltenbreiten wie in Excel
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(25);

        // Datum formatieren
        $sheet->getStyle('B3:B' . ($zeile - 1))
            ->getNumberFormat()
            ->setFormatCode('DD.MM.YYYY');
        // Beträge formatieren
        $sheet->getStyle('D2:D' . ($zeile - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0.00 "€"');

        // Gesamt unten anzeigen
        $sheet->setCellValue('C' . $zeile, 'Gesamtsumme:');
        $sheet->setCellValue('D' . $zeile, $abrechnung['gesamtsumme']);
        $sheet->getStyle('D' . $zeile)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00 "€"');

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