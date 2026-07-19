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

        $ergebnis = $this->parser->parseColeurBund($zeilen);

        $this->assertSame(99.7, $ergebnis['coleur']);
        $this->assertSame(33.0, $ergebnis['bund']);
        // Ohne Preis-/Mengenzeilen trifft keine Positionssumme die Blocksumme
        // → best-effort leer, die Summen bleiben trotzdem nutzbar.
        $this->assertSame([], $ergebnis['coleur_positionen']);
        $this->assertSame([], $ergebnis['bund_positionen']);
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

        $ergebnis = $this->parser->parseColeurBund($zeilen);

        $this->assertSame(12.5, $ergebnis['coleur']);
        $this->assertSame(7.0, $ergebnis['bund']);
    }

    public function testColeurBundNullWennLabelOderWertFehlt(): void
    {
        $this->assertSame(
            ['coleur' => null, 'bund' => null, 'coleur_positionen' => [], 'bund_positionen' => []],
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

    // ==================== GETRÄNKEDETAILS (Issue #63, #59b) ====================

    /**
     * Personen-Sheet mit Getränkespalten wie in der echten Datei:
     * Namen in Zeile 1 (Spalten 2 bis vor "Getränke"), Preise in Zeile 2,
     * Mengen je Person; "Internet*" hat als Zeile-2-Wert den Umlage-Topf (90),
     * der Stückpreis ergibt sich aus dem Rest Betrag − Getränkesumme.
     */
    private function personenZeilenMitGetraenken(): array
    {
        return [
            1 => [1 => '**********', 2 => 'Biere', 3 => 'Spalter', 4 => 'Turmherren', 10 => 'Getränke', 11 => 'Internet*', 12 => 'Ausstehend', 13 => 'Gesamt'],
            2 => [1 => '**********', 2 => 1.3, 3 => 1, 4 => 0.5, 11 => 90],
            3 => [1 => 'Sobkowiak', 2 => 2, 3 => 11, 4 => 0, 10 => 13.6, 11 => 1, 13 => 22.6],
            4 => [1 => 'Achtzehn', 4 => 2, 10 => 1, 13 => 1],
            5 => [1 => '**********'],
        ];
    }

    public function testParstGetraenkePositionenJePerson(): void
    {
        $personen = $this->parser->parsePersonen($this->personenZeilenMitGetraenken());

        // Sobkowiak: 2×1,30 + 11×1,00 = 13,60 Getränke + 9,00 Internet-Rest;
        // Mengen 0 (Turmherren) tauchen nicht auf.
        $this->assertSame([
            ['bezeichnung' => 'Biere', 'anzahl' => 2.0, 'einzelpreis' => 1.3, 'summe' => 2.6],
            ['bezeichnung' => 'Spalter', 'anzahl' => 11.0, 'einzelpreis' => 1.0, 'summe' => 11.0],
            ['bezeichnung' => 'Internet', 'anzahl' => 1.0, 'einzelpreis' => 9.0, 'summe' => 9.0],
        ], $personen[0]['positionen']);

        // Achtzehn: nur Getränke, kein Internet-Rest.
        $this->assertSame(
            [['bezeichnung' => 'Turmherren', 'anzahl' => 2.0, 'einzelpreis' => 0.5, 'summe' => 1.0]],
            $personen[1]['positionen']
        );
    }

    public function testPositionenAuchBeiAbgezogenemAusstehend(): void
    {
        $zeilen = $this->personenZeilenMitGetraenken();
        // Sobkowiak: 5,00 Altbestand → Betrag 22,60 bleibt, Positionen passen weiter.
        $zeilen[3][12] = 5;
        $zeilen[3][13] = 27.6;

        $personen = $this->parser->parsePersonen($zeilen);

        $this->assertSame(22.6, $personen[0]['betrag']);
        $this->assertCount(3, $personen[0]['positionen']);
    }

    public function testPositionenLeerWennGetraenkeSummeNichtPasst(): void
    {
        $zeilen = $this->personenZeilenMitGetraenken();
        // Gecachte Getränkesumme passt nicht zu den Mengen (editierte Datei)
        $zeilen[3][10] = 99;

        $personen = $this->parser->parsePersonen($zeilen);

        $this->assertSame([], $personen[0]['positionen']);
        $this->assertSame(22.6, $personen[0]['betrag']);
    }

    public function testPositionenLeerBeiRestOhneInternet(): void
    {
        $zeilen = $this->personenZeilenMitGetraenken();
        // Rest von 9,00 €, aber keine gezählte Internet-Pauschale → unerklärbar
        unset($zeilen[3][11]);

        $personen = $this->parser->parsePersonen($zeilen);

        $this->assertSame([], $personen[0]['positionen']);
    }

    public function testPositionenLeerOhneGetraenkeSpalte(): void
    {
        // Altes/fremdes Layout ohne "Getränke"-Summenspalte → keine Details,
        // aber die Beträge funktionieren unverändert.
        $personen = $this->parser->parsePersonen([
            1 => [1 => 'X', 5 => 'Ausstehend', 6 => 'Gesamt'],
            3 => [1 => 'Müller', 6 => 10],
            4 => [1 => '***'],
        ]);

        $this->assertSame([], $personen[0]['positionen']);
        $this->assertSame(10.0, $personen[0]['betrag']);
    }

    public function testColeurBundMitPositionen(): void
    {
        // Block wie in der echten Datei: Header (Label+1), Preise (+2), Mengen (+3)
        $zeilen = [
            3 => [3 => 'Coleur:'],
            4 => [4 => 'Biere', 5 => 'Spalter', 6 => 'Gesamt:'],
            5 => [4 => 1.3, 5 => 1],
            6 => [4 => 10, 5 => 5, 6 => 18],
        ];

        $ergebnis = $this->parser->parseColeurBund($zeilen);

        $this->assertSame(18.0, $ergebnis['coleur']);
        $this->assertSame([
            ['bezeichnung' => 'Biere', 'anzahl' => 10.0, 'einzelpreis' => 1.3, 'summe' => 13.0],
            ['bezeichnung' => 'Spalter', 'anzahl' => 5.0, 'einzelpreis' => 1.0, 'summe' => 5.0],
        ], $ergebnis['coleur_positionen']);
    }

    public function testColeurBundPositionenLeerBeiSummenabweichung(): void
    {
        $zeilen = [
            3 => [3 => 'Coleur:'],
            4 => [4 => 'Biere', 6 => 'Gesamt:'],
            5 => [4 => 1.3],
            6 => [4 => 10, 6 => 99],
        ];

        $ergebnis = $this->parser->parseColeurBund($zeilen);

        $this->assertSame(99.0, $ergebnis['coleur']);
        $this->assertSame([], $ergebnis['coleur_positionen']);
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
