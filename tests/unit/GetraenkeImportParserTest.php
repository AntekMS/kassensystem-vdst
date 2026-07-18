<?php

use App\Libraries\GetraenkeRechnungImport;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet den Parser für die Getränkerechnung-Excel (Issue #35).
 *
 * Die Zeilen-Arrays entsprechen dem Format aus sheetZuArray():
 * 1-basiert [zeile][spalte], leere Zellen fehlen.
 *
 * @internal
 */
final class GetraenkeImportParserTest extends CIUnitTestCase
{
    private GetraenkeRechnungImport $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GetraenkeRechnungImport();
    }

    /**
     * Personen-Sheet wie in der echten Datei: Header (1), Preise (2),
     * Personen ab 3, Trennzeile, Summenzeilen.
     */
    private function personenZeilen(): array
    {
        return [
            1 => [1 => '**********', 10 => 'Getränke', 11 => 'Internet*', 12 => 'Ausstehend', 13 => 'Gesamt'],
            2 => [1 => '**********', 11 => 90],
            3 => [1 => 'Achtzehn', 13 => 0.7],
            4 => [1 => 'Kuhn', 13 => 2.9000000000000004],
            5 => [1 => 'Blenk', 13 => 0],
            6 => [1 => ' Sobkowiak ', 13 => 35.4],
            7 => [1 => '(Einfügespalte)', 13 => 0],
            8 => [1 => '**********', 13 => '**********'],
            9 => [1 => 'Gesamtanzahl:', 13 => 999],
            10 => [1 => 'Gesamtsumme:', 13 => 999],
        ];
    }

    private function personenVonZeilen(array $zeilen): array
    {
        $personen = [];
        foreach ($this->parser->parsePersonen($zeilen) as $eintrag) {
            $personen[$eintrag['person']] = $eintrag['betrag'];
        }

        return $personen;
    }

    public function testParstPersonenMitBetraegen(): void
    {
        $personen = $this->personenVonZeilen($this->personenZeilen());

        $this->assertSame(['Achtzehn' => 0.7, 'Kuhn' => 2.9, 'Sobkowiak' => 35.4], $personen);
    }

    public function testAusstehendWirdAbgezogen(): void
    {
        $zeilen = $this->personenZeilen();
        $zeilen[6][12] = 5.4; // Sobkowiak: 35.4 Gesamt, 5.4 davon Altbestand

        $personen = $this->personenVonZeilen($zeilen);

        $this->assertSame(30.0, $personen['Sobkowiak']);
    }

    public function testUeberspringtNullBetraegeUndEinfuegespalte(): void
    {
        $personen = $this->personenVonZeilen($this->personenZeilen());

        $this->assertArrayNotHasKey('Blenk', $personen);
        $this->assertArrayNotHasKey('(Einfügespalte)', $personen);
    }

    public function testStopptAnTrennzeile(): void
    {
        $personen = $this->personenVonZeilen($this->personenZeilen());

        $this->assertArrayNotHasKey('Gesamtanzahl:', $personen);
        $this->assertCount(3, $personen);
    }

    public function testStopptAnGesamtanzahlOhneTrennzeile(): void
    {
        $zeilen = $this->personenZeilen();
        unset($zeilen[8]); // Trennzeile entfernt → Fallback greift

        $personen = $this->personenVonZeilen($zeilen);

        $this->assertCount(3, $personen);
    }

    public function testVariablePersonenanzahl(): void
    {
        $zeilen = [
            1 => [1 => 'X', 5 => 'Ausstehend', 6 => 'Gesamt'],
            2 => [1 => '***'],
        ];
        for ($i = 0; $i < 25; $i++) {
            $zeilen[3 + $i] = [1 => 'Person' . $i, 6 => $i + 1];
        }
        $zeilen[28] = [1 => '***'];

        $this->assertCount(25, $this->parser->parsePersonen($zeilen));
    }

    public function testFehlendeGesamtSpalteWirftException(): void
    {
        $zeilen = $this->personenZeilen();
        unset($zeilen[1][13]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Gesamt/');
        $this->parser->parsePersonen($zeilen);
    }

    public function testFehlendeAusstehendSpalteWirftException(): void
    {
        $zeilen = $this->personenZeilen();
        unset($zeilen[1][12]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Ausstehend/');
        $this->parser->parsePersonen($zeilen);
    }

    public function testKeinePersonenWirftException(): void
    {
        $zeilen = [
            1 => [1 => 'X', 12 => 'Ausstehend', 13 => 'Gesamt'],
            3 => [1 => 'Blenk', 13 => 0],
            4 => [1 => '***'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->parser->parsePersonen($zeilen);
    }

    public function testFindetColeurUndBundSummen(): void
    {
        // Layout wie in der echten Datei: Label → Header-Zeile → Preise → Werte
        $zeilen = [
            3 => [3 => 'Coleur:'],
            4 => [4 => 'Biere', 9 => 'Gesamt:'],
            6 => [9 => 99.7],
            11 => [3 => 'Bund:'],
            12 => [4 => 'Biere', 10 => 'Gesamt:'],
            14 => [10 => 33.0000001],
        ];

        $this->assertSame(['coleur' => 99.7, 'bund' => 33.0], $this->parser->parseColeurBund($zeilen));
    }

    public function testFindetBloeckeAnAnderenPositionen(): void
    {
        $zeilen = [
            1 => [1 => 'coleur'],
            2 => [5 => 'GESAMT'],
            4 => [5 => 12.5],
            8 => [2 => 'Bund:'],
            9 => [3 => 'Gesamt:'],
            11 => [3 => 7],
        ];

        $this->assertSame(['coleur' => 12.5, 'bund' => 7.0], $this->parser->parseColeurBund($zeilen));
    }

    public function testColeurBundNullWennLabelOderWertFehlt(): void
    {
        $this->assertSame(
            ['coleur' => null, 'bund' => null],
            $this->parser->parseColeurBund([1 => [1 => 'irgendwas']])
        );

        // Label da, aber Wert-Zelle leer bzw. kein Zahlenwert
        $zeilen = [
            3 => [3 => 'Coleur:'],
            4 => [9 => 'Gesamt:'],
            6 => [9 => 'kaputt'],
        ];
        $ergebnis = $this->parser->parseColeurBund($zeilen);

        $this->assertNull($ergebnis['coleur']);
    }

    public function testTrennzeilenErkennung(): void
    {
        $this->assertTrue(GetraenkeRechnungImport::istTrennzeile('**********'));
        $this->assertTrue(GetraenkeRechnungImport::istTrennzeile('***'));
        $this->assertTrue(GetraenkeRechnungImport::istTrennzeile('  *  '));
        $this->assertFalse(GetraenkeRechnungImport::istTrennzeile('*Name'));
        $this->assertFalse(GetraenkeRechnungImport::istTrennzeile(''));
    }

    public function testMonatsName(): void
    {
        $this->assertSame('November 2025', GetraenkeRechnungImport::monatsName('2025-11'));
        $this->assertSame('Januar 2026', GetraenkeRechnungImport::monatsName('2026-01'));
    }

    /**
     * Zeitraum-Darstellung (Issue #57): gleiches Jahr → Jahreszahl nur einmal
     * am Ende, Jahreswechsel → beide Jahreszahlen.
     */
    public function testMonatsNameMitZeitraum(): void
    {
        $this->assertSame('November–Dezember 2025', GetraenkeRechnungImport::monatsName('2025-11', '2025-12'));
        $this->assertSame('November 2025–Januar 2026', GetraenkeRechnungImport::monatsName('2025-11', '2026-01'));
        // Gleicher Monat als "Bis" verhält sich wie ein Einzelmonat.
        $this->assertSame('November 2025', GetraenkeRechnungImport::monatsName('2025-11', '2025-11'));
        // Kein "Bis" verhält sich wie bisher.
        $this->assertSame('November 2025', GetraenkeRechnungImport::monatsName('2025-11', null));
    }

    /**
     * Reverse-Parser (Issue #64): parseMonatsName ist das Gegenstück zu
     * monatsName — die Backfill-Migration leitet damit import_monat/
     * import_monat_bis der Bestands-Forderungen aus dem grund ab.
     * Roundtrip über alle drei Formate.
     */
    public function testParseMonatsNameRoundtrip(): void
    {
        $faelle = [
            ['2025-11', null],       // "November 2025"
            ['2025-11', '2025-12'],  // "November–Dezember 2025" (gleiches Jahr)
            ['2025-11', '2026-01'],  // "November 2025–Januar 2026" (Jahreswechsel)
            ['2026-03', null],       // Umlaut-Monat "März 2026"
        ];

        foreach ($faelle as [$von, $bis]) {
            $name = GetraenkeRechnungImport::monatsName($von, $bis);
            $this->assertSame(
                ['von' => $von, 'bis' => $bis],
                GetraenkeRechnungImport::parseMonatsName($name),
                'Roundtrip fehlgeschlagen für "' . $name . '"'
            );
        }

        // bis === von rendert als Einzelmonat → parst zurück zu bis = null
        // (dieselbe Normalisierung wie SchuldModel::importMonatBis).
        $this->assertSame(
            ['von' => '2025-11', 'bis' => null],
            GetraenkeRechnungImport::parseMonatsName(GetraenkeRechnungImport::monatsName('2025-11', '2025-11'))
        );

        // Auch ein handgeschriebener Pseudo-Zeitraum mit bis === von wird
        // normalisiert — sonst entstünde ein von==bis-Markerpaar, das keine
        // Abfrage (alle normalisieren über importMonatBis) je matchen könnte.
        $this->assertSame(
            ['von' => '2025-11', 'bis' => null],
            GetraenkeRechnungImport::parseMonatsName('November–November 2025')
        );
    }

    public function testParseMonatsNameLehntFremdtexteAb(): void
    {
        // Der Rest des GETRAENKE_BEGLICHEN_GRUND nach dem Präfix — darf nie
        // als Import-Zeitraum durchgehen.
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName('beglichen'));
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName('November 2025 (korrigiert)'));
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName('Pfingsten 2025'));
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName('November'));
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName(''));
        // Verdrehter Zeitraum (Ende vor Anfang) wird nicht akzeptiert.
        $this->assertNull(GetraenkeRechnungImport::parseMonatsName('Dezember 2025–November 2025'));
    }

    /**
     * Dateiname→Monat-Ableitung (Issue #57): erkennt deutsche Monatsnamen im
     * Original-Dateinamen, mit oder ohne Jahr; ohne Jahr wird ein plausibles
     * Jahr geschätzt (nie ein weit in der Zukunft liegender Monat).
     */
    public function testMonatAusDateinameMitJahr(): void
    {
        $this->assertSame('2025-11', GetraenkeRechnungImport::monatAusDateiname('GetraenkeNovember2025.xlsx'));
        $this->assertSame('2025-11', GetraenkeRechnungImport::monatAusDateiname('Getraenke_November_2025.xlsx'));
        $this->assertSame('2026-01', GetraenkeRechnungImport::monatAusDateiname('rechnung-januar-2026.xlsx'));
    }

    public function testMonatAusDateinameOhneJahrSchaetztPlausiblesJahr(): void
    {
        // "Heute" = 15. Juli 2026 (Monat 7): November (11) liegt mehr als
        // einen Monat voraus → Vorjahr. Juni (6) liegt in der Vergangenheit
        // desselben Jahres → laufendes Jahr.
        $heute = new \DateTimeImmutable('2026-07-15');

        $this->assertSame(
            '2025-11',
            GetraenkeRechnungImport::monatAusDateiname('GetraenkeNovember.xlsx', $heute)
        );
        $this->assertSame(
            '2026-06',
            GetraenkeRechnungImport::monatAusDateiname('GetraenkeJuni.xlsx', $heute)
        );
        // Der unmittelbar nächste Monat (August, +1) gilt noch als plausibel
        // zeitnah (z.B. Import kurz vor Monatsende) → laufendes Jahr.
        $this->assertSame(
            '2026-08',
            GetraenkeRechnungImport::monatAusDateiname('GetraenkeAugust.xlsx', $heute)
        );
    }

    public function testMonatAusDateinameErkenntUmlautVarianten(): void
    {
        $this->assertSame('2026-03', GetraenkeRechnungImport::monatAusDateiname('RechnungMärz2026.xlsx'));
        $this->assertSame('2026-03', GetraenkeRechnungImport::monatAusDateiname('RechnungMaerz2026.xlsx'));
    }

    public function testMonatAusDateinameOhneMonatsnameGibtNullZurueck(): void
    {
        $this->assertNull(GetraenkeRechnungImport::monatAusDateiname('Getraenkerechnung.xlsx'));
        $this->assertNull(GetraenkeRechnungImport::monatAusDateiname('Rechnung2025.xlsx'));
    }
}
