<?php

use App\Libraries\GetraenkeImportAufloeser;
use App\Models\PersonModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet den DB-losen Nachname→Vollname-Resolver des Getränke-Imports
 * (Issue #62). Der Resolver bekommt die Parser-Personen + eine Nachname-Map
 * + optionale POST-Wahlen und validiert die person_id-Wahlen selbst.
 *
 * @internal
 */
final class GetraenkeImportAufloeserTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    /** Nachname-Map mit einem eindeutigen, einem mehrdeutigen und keinem für „Gast". */
    private function map(): array
    {
        return PersonModel::baueNachnameMap([
            ['id' => 1, 'vorname' => 'Antek', 'nachname' => 'Sobkowiak', 'email' => null],
            ['id' => 2, 'vorname' => 'Anna', 'nachname' => 'Müller', 'email' => null],
            ['id' => 3, 'vorname' => 'Bernd', 'nachname' => 'Müller', 'email' => null],
        ]);
    }

    private function personen(): array
    {
        return [
            ['person' => 'Sobkowiak', 'betrag' => 35.4],
            ['person' => 'Müller', 'betrag' => 10.0],
            ['person' => 'Fremdgast', 'betrag' => 2.5],
        ];
    }

    public function testEindeutigLoestAufVollnameUndId(): void
    {
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map());

        $this->assertSame('Antek Sobkowiak', $aufgeloest[0]['person']);
        $this->assertSame(1, $aufgeloest[0]['person_id']);
        $this->assertSame('eindeutig', $aufgeloest[0]['status']);
        // Betrag wird durchgereicht.
        $this->assertSame(35.4, $aufgeloest[0]['betrag']);
    }

    /** Getränkedetails (Issue #63) hängen am Eintrag und überleben die Auflösung. */
    public function testPositionenWerdenDurchgereicht(): void
    {
        $positionen = [['bezeichnung' => 'Biere', 'anzahl' => 2.0, 'einzelpreis' => 1.3, 'summe' => 2.6]];
        $personen = [['person' => 'Sobkowiak', 'betrag' => 2.6, 'positionen' => $positionen]];

        $aufgeloest = GetraenkeImportAufloeser::loese($personen, $this->map());

        $this->assertSame($positionen, $aufgeloest[0]['positionen']);
        // Parser-Output ohne Positionen (defensive Abwärtskompatibilität) → leere Liste
        $this->assertSame([], GetraenkeImportAufloeser::loese($this->personen(), $this->map())[0]['positionen']);
    }

    public function testMehrdeutigOhneWahlBleibtGast(): void
    {
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map());

        $this->assertSame('mehrdeutig', $aufgeloest[1]['status']);
        $this->assertSame('Müller', $aufgeloest[1]['person']);
        $this->assertNull($aufgeloest[1]['person_id']);
        $this->assertCount(2, $aufgeloest[1]['kandidaten']);
    }

    public function testMehrdeutigMitGueltigerWahl(): void
    {
        $wahlen = [person_schluessel('Müller') => 3];
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map(), $wahlen);

        $this->assertSame(3, $aufgeloest[1]['person_id']);
        $this->assertSame('Bernd Müller', $aufgeloest[1]['person']);
    }

    public function testMehrdeutigMitUngueltigerWahlBleibtGast(): void
    {
        // id 1 (Sobkowiak) ist KEIN Kandidat für „Müller" → wird verworfen.
        $wahlen = [person_schluessel('Müller') => 1];
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map(), $wahlen);

        $this->assertNull($aufgeloest[1]['person_id']);
        $this->assertSame('Müller', $aufgeloest[1]['person']);
    }

    public function testUnbekanntOhneWahlBleibtGast(): void
    {
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map());

        $this->assertSame('unbekannt', $aufgeloest[2]['status']);
        $this->assertSame('Fremdgast', $aufgeloest[2]['person']);
        $this->assertNull($aufgeloest[2]['person_id']);
        $this->assertSame([], $aufgeloest[2]['kandidaten']);
    }

    public function testUnbekanntMitGueltigerPersonWahl(): void
    {
        // Ein Gast kann jeder beliebigen Register-Person zugeordnet werden.
        $wahlen = [person_schluessel('Fremdgast') => 1];
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map(), $wahlen);

        $this->assertSame(1, $aufgeloest[2]['person_id']);
        $this->assertSame('Antek Sobkowiak', $aufgeloest[2]['person']);
    }

    public function testUnbekanntMitUngueltigerIdBleibtGast(): void
    {
        $wahlen = [person_schluessel('Fremdgast') => 999];
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map(), $wahlen);

        $this->assertNull($aufgeloest[2]['person_id']);
        $this->assertSame('Fremdgast', $aufgeloest[2]['person']);
    }

    public function testBrauchtAuswahl(): void
    {
        $aufgeloest = GetraenkeImportAufloeser::loese($this->personen(), $this->map());
        $this->assertTrue(GetraenkeImportAufloeser::brauchtAuswahl($aufgeloest));

        // Nur eindeutige → keine Auswahl nötig.
        $nurEindeutig = GetraenkeImportAufloeser::loese(
            [['person' => 'Sobkowiak', 'betrag' => 1.0]],
            $this->map()
        );
        $this->assertFalse(GetraenkeImportAufloeser::brauchtAuswahl($nurEindeutig));
    }
}
