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
 * Getränkedetails (Issue #63, #59b): zusätzlich zu den Summen werden die
 * Einzelpositionen (Menge je Getränk) gelesen — die Getränkespalten stehen
 * zwischen der Namensspalte und der "Getränke"-Summenspalte, Zeile 1 trägt
 * die Getränkenamen, Zeile 2 die Einzelpreise. Die Internet-Pauschale hat
 * keinen zuverlässigen Preis in Zeile 2 und wird als Rest
 * (Betrag − Getränkesumme) übernommen. Positionen sind BEST-EFFORT: stimmt
 * ihre Summe nicht mit den gecachten Summenwerten überein (editierte Datei,
 * unerklärbarer Rest), bleibt die Positionsliste leer und nur der Endbetrag
 * zählt — die Beträge selbst werden nie aus den Positionen berechnet.
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
     * Toleranz für den Abgleich Positionssumme ↔ gecachte Summenwerte —
     * deckt Float-Rauschen der gecachten Formelwerte ab (z.B. 2.9000000000004).
     */
    private const SUMMEN_TOLERANZ = 0.011;

    /**
     * Lädt die xlsx und parst beide Sheets.
     *
     * @return array{personen: list<array{person: string, betrag: float, positionen: list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>}>, coleur: ?float, bund: ?float, coleur_positionen: list<array>, bund_positionen: list<array>}
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
     * @return list<array{person: string, betrag: float, positionen: list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>}>
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

        // Getränkedetails (Issue #63): Spalten 2 bis vor "Getränke" sind die
        // Getränke (Name in Zeile 1, Einzelpreis in Zeile 2); "Internet*" hat
        // keinen verlässlichen Zeile-2-Preis und läuft über den Rest-Betrag.
        $getraenkeSpalte = self::findeSpalte($kopfzeile, 'Getränke');
        $internetSpalte = self::findeSpalteMitPraefix($kopfzeile, 'internet');
        $getraenkeSpalten = $this->leseGetraenkeSpalten($kopfzeile, $zeilen[self::ERSTE_PERSONEN_ZEILE - 1] ?? [], $getraenkeSpalte);

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

            $personen[] = [
                'person' => $name,
                'betrag' => $betrag,
                'positionen' => $getraenkeSpalte === null ? [] : $this->lesePositionen(
                    $zeilen[$zeile],
                    $getraenkeSpalten,
                    self::alsZahl($zeilen[$zeile][$getraenkeSpalte] ?? null),
                    $internetSpalte,
                    $betrag
                ),
            ];
        }

        if ($personen === []) {
            throw new \RuntimeException('Es wurden keine Personen mit Beträgen gefunden — ist das Sheet leer?');
        }

        return $personen;
    }

    /**
     * Sammelt die Getränkespalten (Spalte, Bezeichnung, Einzelpreis) zwischen
     * der Namensspalte und der "Getränke"-Summenspalte.
     *
     * @return list<array{spalte: int, bezeichnung: string, einzelpreis: float}>
     */
    private function leseGetraenkeSpalten(array $kopfzeile, array $preisZeile, ?int $getraenkeSpalte): array
    {
        if ($getraenkeSpalte === null) {
            return [];
        }

        $spalten = [];
        for ($spalte = 2; $spalte < $getraenkeSpalte; $spalte++) {
            $bezeichnung = trim((string) ($kopfzeile[$spalte] ?? ''));
            if ($bezeichnung === '' || self::istTrennzeile($bezeichnung)) {
                continue;
            }

            $spalten[] = [
                'spalte' => $spalte,
                'bezeichnung' => $bezeichnung,
                'einzelpreis' => self::alsZahl($preisZeile[$spalte] ?? null),
            ];
        }

        return $spalten;
    }

    /**
     * Liest die Einzelpositionen einer Personen-Zeile (Menge × Einzelpreis je
     * Getränk, Issue #63). Zwei Konsistenz-Checks entscheiden, ob die Details
     * vertrauenswürdig sind — sonst leere Liste (nur der Endbetrag zählt):
     * 1. Die Summe der Getränkepositionen muss die gecachte "Getränke"-Summe
     *    der Zeile treffen.
     * 2. Ein Rest zum Endbetrag ist nur mit gezählter Internet-Pauschale
     *    erklärbar (deren Zeile-2-"Preis" ist der Umlage-Topf, nicht der
     *    Stückpreis — der Stückpreis ergibt sich aus dem Rest).
     *
     * @param array<int, mixed> $zeile
     * @param list<array{spalte: int, bezeichnung: string, einzelpreis: float}> $getraenkeSpalten
     * @return list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>
     */
    private function lesePositionen(array $zeile, array $getraenkeSpalten, float $getraenkeSumme, ?int $internetSpalte, float $betrag): array
    {
        $positionen = [];
        foreach ($getraenkeSpalten as $getraenk) {
            $anzahl = $zeile[$getraenk['spalte']] ?? null;
            if (!is_numeric($anzahl) || abs((float) $anzahl) < 0.005) {
                continue;
            }

            $positionen[] = [
                'bezeichnung' => $getraenk['bezeichnung'],
                'anzahl' => (float) $anzahl,
                'einzelpreis' => $getraenk['einzelpreis'],
                'summe' => round((float) $anzahl * $getraenk['einzelpreis'], 2),
            ];
        }

        $positionsSumme = round(array_sum(array_column($positionen, 'summe')), 2);
        if (abs($positionsSumme - $getraenkeSumme) > self::SUMMEN_TOLERANZ) {
            return [];
        }

        $rest = round($betrag - $positionsSumme, 2);
        if (abs($rest) <= self::SUMMEN_TOLERANZ) {
            return $positionen;
        }

        $internetAnzahl = $internetSpalte === null ? 0.0 : self::alsZahl($zeile[$internetSpalte] ?? null);
        if ($rest < 0 || $internetAnzahl <= 0) {
            return [];
        }

        $positionen[] = [
            'bezeichnung' => 'Internet',
            'anzahl' => $internetAnzahl,
            'einzelpreis' => round($rest / $internetAnzahl, 2),
            'summe' => $rest,
        ];

        return $positionen;
    }

    /**
     * Parst das Coleur-&-Bund-Sheet: sucht die Label-Zellen "Coleur:"/"Bund:",
     * darunter die "Gesamt:"-Überschrift und liest den Wert zwei Zeilen unter
     * der Überschrift (= Label-Zeile + 3). Nicht gefunden → null.
     *
     * Zusätzlich die Einzelpositionen des Blocks (Issue #63): zwischen Label-
     * und "Gesamt:"-Spalte stehen die Getränke (Name in der Header-Zeile,
     * Preis eine Zeile tiefer, Menge in der Summen-Zeile). Best-effort wie im
     * Personen-Sheet: Positionssumme ≠ Blocksumme → leere Liste.
     *
     * @param array<int, array<int, mixed>> $zeilen 1-basiert [zeile][spalte]
     * @return array{coleur: ?float, bund: ?float, coleur_positionen: list<array>, bund_positionen: list<array>}
     */
    public function parseColeurBund(array $zeilen): array
    {
        $coleur = $this->findeBlock($zeilen, 'coleur');
        $bund = $this->findeBlock($zeilen, 'bund');

        return [
            'coleur' => $coleur['summe'],
            'bund' => $bund['summe'],
            'coleur_positionen' => $coleur['positionen'],
            'bund_positionen' => $bund['positionen'],
        ];
    }

    /**
     * Deutsche Monatsnamen (Muster fürs Regex-Matching in monatAusDateiname).
     */
    private const MONATSNAMEN = [
        '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
        '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
    ];

    /**
     * Regex-Muster je Monat für die Dateinamen-Erkennung (ASCII-Variante für
     * Umlaute mit eingerechnet, z.B. "Maerz" statt "März").
     */
    private const MONATSNAMEN_MUSTER = [
        '01' => 'januar', '02' => 'februar', '03' => 'm(ä|ae)rz', '04' => 'april',
        '05' => 'mai', '06' => 'juni', '07' => 'juli', '08' => 'august',
        '09' => 'september', '10' => 'oktober', '11' => 'november', '12' => 'dezember',
    ];

    /**
     * Monatsname zu "YYYY-MM", z.B. "2025-11" → "November 2025". Mit
     * optionalem Endmonat wird ein Zeitraum dargestellt (Issue #57):
     * "November–Dezember 2025" (gleiches Jahr) bzw.
     * "November 2025–Januar 2026" (Jahreswechsel).
     */
    public static function monatsName(string $yyyyMm, ?string $bisYyyyMm = null): string
    {
        $name = self::einzelMonatsName($yyyyMm);

        if ($bisYyyyMm === null || $bisYyyyMm === $yyyyMm) {
            return $name;
        }

        $bisName = self::einzelMonatsName($bisYyyyMm);
        [$jahrVon] = array_pad(explode('-', $yyyyMm, 2), 2, '');
        [$jahrBis] = array_pad(explode('-', $bisYyyyMm, 2), 2, '');

        if ($jahrVon !== '' && $jahrVon === $jahrBis) {
            // Gleiches Jahr: Jahreszahl nur einmal am Ende, nicht doppelt.
            $name = preg_replace('/\s+\d{4}$/', '', $name);
        }

        return $name . '–' . $bisName;
    }

    /**
     * Einzelner Monatsname ohne Zeitraum-Logik.
     */
    private static function einzelMonatsName(string $yyyyMm): string
    {
        [$jahr, $monat] = array_pad(explode('-', $yyyyMm, 2), 2, '');

        return (self::MONATSNAMEN[$monat] ?? $yyyyMm) . ($jahr !== '' ? ' ' . $jahr : '');
    }

    /**
     * Leitet aus einem Original-Dateinamen (z.B. "GetraenkeNovember2025.xlsx")
     * einen Monatsvorschlag "YYYY-MM" ab, oder null, wenn kein deutscher
     * Monatsname im Dateinamen erkannt wird (Issue #57).
     *
     * Steht kein Jahr im Namen, wird ein plausibles Jahr geschätzt: das
     * laufende Jahr, außer der erkannte Monat läge mehr als einen Monat in
     * der Zukunft — Getränkerechnungen werden erfahrungsgemäß zeitnah nach
     * Monatsende importiert, ein weit voraus liegender Monat gehört daher
     * eher zum Vorjahr.
     */
    public static function monatAusDateiname(string $dateiname, ?\DateTimeImmutable $heute = null): ?string
    {
        $stamm = mb_strtolower(pathinfo($dateiname, PATHINFO_FILENAME));

        $monat = null;
        foreach (self::MONATSNAMEN_MUSTER as $nr => $muster) {
            if (preg_match('/' . $muster . '/u', $stamm) === 1) {
                $monat = $nr;
                break;
            }
        }

        if ($monat === null) {
            return null;
        }

        if (preg_match('/(20\d{2})/', $stamm, $treffer) === 1) {
            $jahr = (int) $treffer[1];
        } else {
            $heute ??= new \DateTimeImmutable();
            $jahr = (int) $heute->format('Y');

            if ((int) $monat > (int) $heute->format('n') + 1) {
                $jahr--;
            }
        }

        return sprintf('%04d-%s', $jahr, $monat);
    }

    /**
     * Gegenstück zu monatsName() (Issue #64): parst einen deutschen Monatsnamen
     * bzw. Zeitraum zurück nach "YYYY-MM" — genutzt von
     * SchuldModel::importMarkerAusGrund() (manuell nachgetragene Forderungen
     * mit kanonischem Import-grund bekommen so ihre Marker); die
     * Backfill-Migration 2026-07-18 trägt eine eingefrorene Kopie.
     *
     * Versteht genau die drei monatsName()-Formate ("November 2025",
     * "November–Dezember 2025", "November 2025–Januar 2026"); alles andere → null.
     * bis === von wird — wie überall (SchuldModel::importMonatBis) — zu
     * NULL normalisiert, damit nie ein unmatchbares von==bis-Paar entsteht.
     *
     * @return ?array{von: string, bis: ?string}
     */
    public static function parseMonatsName(string $name): ?array
    {
        $teile = explode('–', trim($name), 2);

        $bis = null;
        if (isset($teile[1])) {
            $bis = self::parseEinzelMonatsName(trim($teile[1]));
            if ($bis === null) {
                return null;
            }
        }

        $vonTeil = trim($teile[0]);
        // Zeitraum im gleichen Jahr: links steht nur der Monatsname, das Jahr
        // liefert der Endmonat ("November–Dezember 2025").
        if ($bis !== null && array_search($vonTeil, self::MONATSNAMEN, true) !== false) {
            $vonTeil .= ' ' . substr($bis, 0, 4);
        }

        $von = self::parseEinzelMonatsName($vonTeil);
        if ($von === null || ($bis !== null && $bis < $von)) {
            return null;
        }

        return ['von' => $von, 'bis' => $bis === $von ? null : $bis];
    }

    /**
     * "November 2025" → "2025-11", sonst null.
     */
    private static function parseEinzelMonatsName(string $name): ?string
    {
        if (preg_match('/^(\p{L}+) (\d{4})$/u', $name, $treffer) !== 1) {
            return null;
        }

        $nummer = array_search($treffer[1], self::MONATSNAMEN, true);

        return $nummer === false ? null : $treffer[2] . '-' . $nummer;
    }

    /**
     * Erkennt die Trennzeile vor den Summenzeilen (nur Asterisken).
     */
    public static function istTrennzeile(string $wert): bool
    {
        return preg_match('/^\*+$/', trim($wert)) === 1;
    }

    /**
     * Sucht einen Block (Label → "Gesamt:"-Header → Wert) samt Einzelpositionen.
     * Blocklayout: Label-Zeile, darunter Header (Getränkenamen + "Gesamt:"),
     * darunter Preise, darunter Mengen mit der Summe in der "Gesamt:"-Spalte.
     *
     * @return array{summe: ?float, positionen: list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>}
     */
    private function findeBlock(array $zeilen, string $label): array
    {
        $leer = ['summe' => null, 'positionen' => []];

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
                    if (!is_numeric($wert)) {
                        return $leer;
                    }

                    $summe = round((float) $wert, 2);

                    return [
                        'summe' => $summe,
                        'positionen' => $this->leseBlockPositionen($zeilen, $zeile + 1, $spalte + 1, $g, $summe),
                    ];
                }

                return $leer;
            }
        }

        return $leer;
    }

    /**
     * Einzelpositionen eines Coleur-/Bund-Blocks: Getränkespalten zwischen
     * Label- und "Gesamt:"-Spalte; Mengen 0 werden übersprungen. Trifft die
     * Positionssumme die Blocksumme nicht, leere Liste (best-effort).
     *
     * @return list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>
     */
    private function leseBlockPositionen(array $zeilen, int $headerZeile, int $ersteSpalte, int $gesamtSpalte, float $blockSumme): array
    {
        $positionen = [];

        for ($spalte = $ersteSpalte; $spalte < $gesamtSpalte; $spalte++) {
            $bezeichnung = trim((string) ($zeilen[$headerZeile][$spalte] ?? ''));
            $anzahl = $zeilen[$headerZeile + 2][$spalte] ?? null;

            if ($bezeichnung === '' || !is_numeric($anzahl) || abs((float) $anzahl) < 0.005) {
                continue;
            }

            $einzelpreis = self::alsZahl($zeilen[$headerZeile + 1][$spalte] ?? null);
            $positionen[] = [
                'bezeichnung' => $bezeichnung,
                'anzahl' => (float) $anzahl,
                'einzelpreis' => $einzelpreis,
                'summe' => round((float) $anzahl * $einzelpreis, 2),
            ];
        }

        $positionsSumme = round(array_sum(array_column($positionen, 'summe')), 2);

        return abs($positionsSumme - $blockSumme) > self::SUMMEN_TOLERANZ ? [] : $positionen;
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

    /**
     * Spaltenindex per Header-Präfix (case-insensitiv) — für die
     * "Internet*"-Spalte, deren Fußnoten-Sternchen nicht zum Namen gehört.
     */
    private static function findeSpalteMitPraefix(array $kopfzeile, string $praefix): ?int
    {
        foreach ($kopfzeile as $spalte => $wert) {
            if (str_starts_with(mb_strtolower(trim((string) $wert)), mb_strtolower($praefix))) {
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
