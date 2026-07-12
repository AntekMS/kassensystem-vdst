<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * GetraenkeRechnungImport - Parser für die monatliche Getränkerechnung (xlsx)
 *
 * Liest die Excel des Getränkewarts (Issue #35):
 * - Sheet "Bundesbrüder & Gäste": pro Zeile ein Nachname mit Endbetrag;
 *   importiert wird "Gesamt" MINUS "Ausstehend" (Altbestände führt bereits
 *   die Schuldenliste im System, sonst würden sie doppelt zählen).
 * - Sheet "Coleur & Bund": zwei Gesamtsummen für die AH-Abrechnung.
 *
 * WICHTIG: Es werden ausschließlich die in der Datei gecachten Formelwerte
 * gelesen (getOldCalculatedValue), NIE getCalculatedValue()/toArray() —
 * die übrigen Sheets enthalten TRANSPOSE-Formeln, an denen die
 * PhpSpreadsheet-Berechnung scheitert. Deshalb werden auch nur die zwei
 * benötigten Sheets überhaupt geladen.
 */
class GetraenkeRechnungImport
{
    public const SHEET_PERSONEN = 'Bundesbrüder & Gäste';
    public const SHEET_COLEUR_BUND = 'Coleur & Bund';

    /**
     * Zeile, in der die Personen beginnen (Zeile 1 = Header, Zeile 2 = Preise).
     */
    private const ERSTE_PERSONEN_ZEILE = 3;

    /**
     * Suchbereich für die Coleur-/Bund-Labels.
     */
    private const SUCHBEREICH_ZEILEN = 30;
    private const SUCHBEREICH_SPALTEN = 15;

    /**
     * Lädt die xlsx und parst beide Sheets.
     *
     * @return array{personen: list<array{person: string, betrag: float}>, coleur: ?float, bund: ?float}
     * @throws \RuntimeException bei unlesbarer Datei oder fehlenden Sheets/Spalten
     */
    public function parseDatei(string $pfad): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([self::SHEET_PERSONEN, self::SHEET_COLEUR_BUND]);
            $spreadsheet = $reader->load($pfad);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Die Datei konnte nicht als Excel-Datei gelesen werden. Ist es die richtige Getränkerechnung (.xlsx)?'
            );
        }

        try {
            $personenSheet = $spreadsheet->getSheetByName(self::SHEET_PERSONEN);
            $coleurBundSheet = $spreadsheet->getSheetByName(self::SHEET_COLEUR_BUND);

            foreach ([self::SHEET_PERSONEN => $personenSheet, self::SHEET_COLEUR_BUND => $coleurBundSheet] as $name => $sheet) {
                if ($sheet === null) {
                    throw new \RuntimeException(
                        'Das Sheet "' . $name . '" wurde nicht gefunden — ist das die richtige Datei?'
                    );
                }
            }

            $ergebnis = [
                'personen' => $this->parsePersonen($this->sheetZuArray($personenSheet)),
            ];

            return $ergebnis + $this->parseColeurBund($this->sheetZuArray($coleurBundSheet));
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Parst das Personen-Sheet (als Zeilen-Array aus sheetZuArray).
     *
     * Die Personenanzahl ist variabel: gelesen wird ab Zeile 3 bis zur
     * Trennzeile (nur Asterisken) bzw. — falls die fehlt — bis zur
     * "Gesamtanzahl:"-Zeile. Die Platzhalterzeile "(Einfügespalte)" und
     * Personen ohne Betrag werden übersprungen.
     *
     * @param array<int, array<int, mixed>> $zeilen 1-basiert [zeile][spalte]
     * @return list<array{person: string, betrag: float}>
     * @throws \RuntimeException wenn Spalten fehlen oder keine Person einen Betrag hat
     */
    public function parsePersonen(array $zeilen): array
    {
        $kopfzeile = $zeilen[1] ?? [];
        $gesamtSpalte = self::findeSpalte($kopfzeile, 'Gesamt');
        $ausstehendSpalte = self::findeSpalte($kopfzeile, 'Ausstehend');

        if ($gesamtSpalte === null) {
            throw new \RuntimeException('Die Spalte "Gesamt" wurde im Sheet "' . self::SHEET_PERSONEN . '" nicht gefunden.');
        }
        if ($ausstehendSpalte === null) {
            throw new \RuntimeException('Die Spalte "Ausstehend" wurde im Sheet "' . self::SHEET_PERSONEN . '" nicht gefunden.');
        }

        $personen = [];
        $letzteZeile = empty($zeilen) ? 0 : max(array_keys($zeilen));

        for ($zeile = self::ERSTE_PERSONEN_ZEILE; $zeile <= $letzteZeile; $zeile++) {
            $name = trim((string) ($zeilen[$zeile][1] ?? ''));

            if (self::istTrennzeile($name) || str_starts_with(mb_strtolower($name), 'gesamt')) {
                break;
            }
            if ($name === '' || $name === '(Einfügespalte)') {
                continue;
            }

            $gesamt = self::alsZahl($zeilen[$zeile][$gesamtSpalte] ?? null);
            $ausstehend = self::alsZahl($zeilen[$zeile][$ausstehendSpalte] ?? null);
            $betrag = round($gesamt - $ausstehend, 2);

            if (abs($betrag) < 0.005) {
                continue;
            }

            $personen[] = ['person' => $name, 'betrag' => $betrag];
        }

        if ($personen === []) {
            throw new \RuntimeException('Es wurden keine Personen mit Beträgen gefunden — ist das Sheet leer?');
        }

        return $personen;
    }

    /**
     * Parst das Coleur-&-Bund-Sheet: sucht die Label-Zellen "Coleur:"/"Bund:",
     * darunter die "Gesamt:"-Überschrift und liest den Wert zwei Zeilen unter
     * der Überschrift (= Label-Zeile + 3). Nicht gefunden → null.
     *
     * @param array<int, array<int, mixed>> $zeilen 1-basiert [zeile][spalte]
     * @return array{coleur: ?float, bund: ?float}
     */
    public function parseColeurBund(array $zeilen): array
    {
        return [
            'coleur' => $this->findeBlockSumme($zeilen, 'coleur'),
            'bund' => $this->findeBlockSumme($zeilen, 'bund'),
        ];
    }

    /**
     * Monatsname zu "YYYY-MM", z.B. "2025-11" → "November 2025".
     */
    public static function monatsName(string $yyyyMm): string
    {
        $monate = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
        ];

        [$jahr, $monat] = array_pad(explode('-', $yyyyMm, 2), 2, '');

        return ($monate[$monat] ?? $yyyyMm) . ($jahr !== '' ? ' ' . $jahr : '');
    }

    /**
     * Erkennt die Trennzeile vor den Summenzeilen (nur Asterisken).
     */
    public static function istTrennzeile(string $wert): bool
    {
        return preg_match('/^\*+$/', trim($wert)) === 1;
    }

    /**
     * Sucht die Summe eines Blocks (Label → "Gesamt:"-Header → Wert).
     */
    private function findeBlockSumme(array $zeilen, string $label): ?float
    {
        for ($zeile = 1; $zeile <= self::SUCHBEREICH_ZEILEN; $zeile++) {
            for ($spalte = 1; $spalte <= self::SUCHBEREICH_SPALTEN; $spalte++) {
                if (self::normalisiertesLabel($zeilen[$zeile][$spalte] ?? null) !== $label) {
                    continue;
                }

                // "Gesamt:"-Header in der Zeile unter dem Label suchen
                for ($g = $spalte; $g <= $spalte + self::SUCHBEREICH_SPALTEN; $g++) {
                    if (self::normalisiertesLabel($zeilen[$zeile + 1][$g] ?? null) !== 'gesamt') {
                        continue;
                    }

                    $wert = $zeilen[$zeile + 3][$g] ?? null;

                    return is_numeric($wert) ? round((float) $wert, 2) : null;
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Liest ein Sheet als 1-basiertes [zeile][spalte]-Array mit Cached Values.
     */
    private function sheetZuArray(Worksheet $sheet): array
    {
        $zeilen = [];
        $letzteSpalte = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        foreach ($sheet->getRowIterator() as $row) {
            $zeilenIndex = $row->getRowIndex();

            for ($spalte = 1; $spalte <= $letzteSpalte; $spalte++) {
                $zelle = $sheet->getCell([$spalte, $zeilenIndex]);

                // Formelzellen: NUR den in der Datei gecachten Wert lesen —
                // getCalculatedValue() würde neu rechnen und scheitern
                $wert = $zelle->getDataType() === DataType::TYPE_FORMULA
                    ? $zelle->getOldCalculatedValue()
                    : $zelle->getValue();

                if ($wert !== null && $wert !== '') {
                    $zeilen[$zeilenIndex][$spalte] = $wert;
                }
            }
        }

        return $zeilen;
    }

    /**
     * Zellwert getrimmt, kleingeschrieben, ohne Doppelpunkt am Ende.
     */
    private static function normalisiertesLabel($wert): string
    {
        return mb_strtolower(rtrim(trim((string) $wert), ':'));
    }

    /**
     * Spaltenindex per Header-Text (case-insensitiv, getrimmt), sonst null.
     */
    private static function findeSpalte(array $kopfzeile, string $titel): ?int
    {
        $gesucht = mb_strtolower($titel);

        foreach ($kopfzeile as $spalte => $wert) {
            if (mb_strtolower(trim((string) $wert)) === $gesucht) {
                return (int) $spalte;
            }
        }

        return null;
    }

    private static function alsZahl($wert): float
    {
        return is_numeric($wert) ? (float) $wert : 0.0;
    }
}
