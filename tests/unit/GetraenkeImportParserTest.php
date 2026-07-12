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
}
