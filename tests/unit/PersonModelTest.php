<?php

use App\Models\PersonModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die DB-losen Seams des Personen-Registers (Issue #61).
 *
 * anzeigename()/baueSchluesselMap()/idAusMap()/emailsAusMap() kapseln die
 * gesamte Match-Logik (Soft-Link person_id, E-Mail-Auflösung) und sind so
 * ohne Datenbank prüfbar. Match läuft ausschließlich über person_schluessel().
 *
 * @internal
 */
final class PersonModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    /** @return array<array> */
    private function fixtures(): array
    {
        return [
            ['id' => 1, 'vorname' => 'Antek', 'nachname' => 'Sobkowiak', 'email' => 'antek@example.org'],
            ['id' => 2, 'vorname' => null, 'nachname' => 'Kuhn', 'email' => null],
            ['id' => 3, 'vorname' => '', 'nachname' => 'Meier [Gast]', 'email' => 'gast@example.org'],
        ];
    }

    public function testAnzeigenameFuegtVorUndNachnamenZusammen(): void
    {
        $this->assertSame('Antek Sobkowiak', PersonModel::anzeigename(['vorname' => 'Antek', 'nachname' => 'Sobkowiak']));
        $this->assertSame('Kuhn', PersonModel::anzeigename(['vorname' => null, 'nachname' => 'Kuhn']));
        $this->assertSame('Kuhn', PersonModel::anzeigename(['vorname' => '', 'nachname' => 'Kuhn']));
        // Whitespace wird kollabiert (person_normalisiere).
        $this->assertSame('Hans Meier', PersonModel::anzeigename(['vorname' => ' Hans ', 'nachname' => ' Meier ']));
    }

    public function testBaueSchluesselMapKeyedNachSchluessel(): void
    {
        $map = PersonModel::baueSchluesselMap($this->fixtures());

        $this->assertArrayHasKey(person_schluessel('Antek Sobkowiak'), $map);
        $this->assertArrayHasKey(person_schluessel('Kuhn'), $map);
        $this->assertSame(1, (int) $map[person_schluessel('antek  sobkowiak')]['id']);
    }

    public function testBaueSchluesselMapErsteZeileGewinntBeiKollision(): void
    {
        $map = PersonModel::baueSchluesselMap([
            ['id' => 10, 'vorname' => null, 'nachname' => 'Schmidt', 'email' => null],
            ['id' => 11, 'vorname' => null, 'nachname' => 'schmidt', 'email' => null],
        ]);

        $this->assertCount(1, $map);
        $this->assertSame(10, (int) $map[person_schluessel('Schmidt')]['id']);
    }

    public function testIdAusMapLoestSoftLinkAufUndFaelltAufNullZurueck(): void
    {
        $map = PersonModel::baueSchluesselMap($this->fixtures());

        // Case-/Whitespace-tolerant über person_schluessel().
        $this->assertSame(1, PersonModel::idAusMap($map, 'antek   sobkowiak'));
        $this->assertSame(2, PersonModel::idAusMap($map, 'Kuhn'));
        // Gäste-Name mit Sonderzeichen bleibt matchbar.
        $this->assertSame(3, PersonModel::idAusMap($map, 'Meier [Gast]'));
        // Kein Registereintrag (Gast/Institution) → NULL.
        $this->assertNull(PersonModel::idAusMap($map, 'AH²-Bund'));
        $this->assertNull(PersonModel::idAusMap($map, 'Unbekannt'));
    }

    public function testEmailsAusMapNurGesuchteMitAdresse(): void
    {
        $map = PersonModel::baueSchluesselMap($this->fixtures());

        $emails = PersonModel::emailsAusMap($map, ['Antek Sobkowiak', 'Kuhn', 'Meier [Gast]']);

        // Sobkowiak + Gast haben eine Adresse, Kuhn nicht.
        $this->assertSame('antek@example.org', $emails[person_schluessel('Antek Sobkowiak')]);
        $this->assertSame('gast@example.org', $emails[person_schluessel('Meier [Gast]')]);
        $this->assertArrayNotHasKey(person_schluessel('Kuhn'), $emails);
    }

    public function testEmailsAusMapIgnoriertNichtGesuchteNamen(): void
    {
        $map = PersonModel::baueSchluesselMap($this->fixtures());

        $emails = PersonModel::emailsAusMap($map, ['Kuhn']);

        $this->assertSame([], $emails);
    }

    public function testBaueNachnameMapGruppiertNachNachname(): void
    {
        $map = PersonModel::baueNachnameMap($this->fixtures());

        // Ein Eintrag je Nachname; case-/whitespace-toleranter Schlüssel.
        $this->assertCount(1, $map[person_schluessel('Sobkowiak')]);
        $this->assertSame(1, (int) $map[person_schluessel('sobkowiak')][0]['id']);
        $this->assertArrayHasKey(person_schluessel('Kuhn'), $map);
    }

    public function testBaueNachnameMapErkenntMehrdeutigkeit(): void
    {
        // Zwei Personen mit gleichem Nachnamen → Liste mit 2 (Mehrdeutigkeit).
        $map = PersonModel::baueNachnameMap([
            ['id' => 1, 'vorname' => 'Anna', 'nachname' => 'Müller', 'email' => null],
            ['id' => 2, 'vorname' => 'Bernd', 'nachname' => 'müller', 'email' => null],
            ['id' => 3, 'vorname' => null, 'nachname' => 'Kuhn', 'email' => null],
        ]);

        $this->assertCount(2, $map[person_schluessel('Müller')]);
        $this->assertCount(1, $map[person_schluessel('Kuhn')]);
    }

    public function testBaueNachnameMapUeberspringtLeereNachnamen(): void
    {
        $map = PersonModel::baueNachnameMap([
            ['id' => 1, 'vorname' => 'X', 'nachname' => '', 'email' => null],
            ['id' => 2, 'vorname' => null, 'nachname' => '  ', 'email' => null],
        ]);

        $this->assertSame([], $map);
    }

    public function testEmailAktionSetztNeueOderGeaenderteAdresse(): void
    {
        $person = ['id' => 1, 'nachname' => 'Sobkowiak', 'email' => 'alt@example.org'];

        $this->assertSame('setzen', PersonModel::emailAktion($person, 'neu@example.org'));
        // Person ohne Adresse bekommt eine → ebenfalls setzen.
        $this->assertSame('setzen', PersonModel::emailAktion(['id' => 2, 'email' => null], 'neu@example.org'));
        // Identische Adresse → nichts zu tun.
        $this->assertSame('nichts', PersonModel::emailAktion($person, 'alt@example.org'));
        $this->assertSame('nichts', PersonModel::emailAktion($person, '  alt@example.org  '));
    }

    public function testEmailAktionLeeresFeldLoeschtGespeicherteAdresse(): void
    {
        // Expliziter Edit: leer LÖSCHT (anders als upsertFuerName).
        $this->assertSame('loeschen', PersonModel::emailAktion(['id' => 1, 'email' => 'alt@example.org'], ''));
        $this->assertSame('loeschen', PersonModel::emailAktion(['id' => 1, 'email' => 'alt@example.org'], '   '));
        // Nichts gespeichert + nichts gepostet → nichts.
        $this->assertSame('nichts', PersonModel::emailAktion(['id' => 1, 'email' => null], ''));
        $this->assertSame('nichts', PersonModel::emailAktion(['id' => 1, 'email' => ''], ''));
    }

    public function testEmailAktionUnbekannterNameWirdAngelegt(): void
    {
        $this->assertSame('anlegen', PersonModel::emailAktion(null, 'neu@example.org'));
        // Unbekannt + leer → kein Register-Eintrag anlegen.
        $this->assertSame('nichts', PersonModel::emailAktion(null, ''));
    }
}
