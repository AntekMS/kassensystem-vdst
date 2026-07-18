<?php

use App\Models\SchuldModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die Erkennung von 1-Klick-Getränkeausgleichen (Issue #43).
 *
 * @internal
 */
final class GetraenkeBeglichenTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label'); // person_schluessel() für istInstitution()
    }

    /**
     * Ein Eintrag, wie ihn erstelleGetraenkeAusgleich() anlegt.
     */
    private function markerEintrag(array $overrides = []): array
    {
        return array_merge([
            'typ' => 'forderung',
            'kategorie' => 'getraenke',
            'betrag' => '-12.50',
            'grund' => SchuldModel::GETRAENKE_BEGLICHEN_GRUND,
            'beleg_id' => null,
            'buchung_id' => null,
            'abrechnung_id' => null,
        ], $overrides);
    }

    public function testErkenntMarkerEintrag(): void
    {
        $this->assertTrue(SchuldModel::istGetraenkeAusgleich($this->markerEintrag()));
    }

    public function testPositiverBetragIstKeinAusgleich(): void
    {
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['betrag' => '12.50'])));
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['betrag' => '0'])));
    }

    public function testAndereKategorieOderTypIstKeinAusgleich(): void
    {
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['kategorie' => 'sonstige'])));
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['typ' => 'verbindlichkeit'])));
    }

    public function testAndererGrundIstKeinAusgleich(): void
    {
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['grund' => 'Rückzahlung bar'])));
    }

    public function testAutomatischeEintraegeSindKeinAusgleich(): void
    {
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['beleg_id' => 7])));
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['buchung_id' => 7])));
        $this->assertFalse(SchuldModel::istGetraenkeAusgleich($this->markerEintrag(['abrechnung_id' => 7])));
    }

    /**
     * Endmonat-Normalisierung der Import-Marker (Issue #64): bis === von ist
     * ein Einzelmonat und wird als NULL gespeichert/gematcht — dieselbe
     * Semantik wie GetraenkeRechnungImport::monatsName().
     */
    public function testImportMonatBisNormalisierung(): void
    {
        $this->assertNull(SchuldModel::importMonatBis('2025-11', null));
        $this->assertNull(SchuldModel::importMonatBis('2025-11', '2025-11'));
        $this->assertSame('2025-12', SchuldModel::importMonatBis('2025-11', '2025-12'));
    }

    /**
     * Marker-Ableitung für manuell angelegte Forderungen (Issue #64-Nachtrag):
     * kanonischer Import-grund → Marker des Monats/Zeitraums, alles andere
     * (fremder grund, falscher typ/kategorie, Beglichen-Marker) → keine Marker.
     */
    public function testImportMarkerAusGrund(): void
    {
        $keine = ['import_monat' => null, 'import_monat_bis' => null];

        $this->assertSame(
            ['import_monat' => '2025-11', 'import_monat_bis' => null],
            SchuldModel::importMarkerAusGrund('forderung', 'getraenke', 'Getränkerechnung November 2025')
        );
        $this->assertSame(
            ['import_monat' => '2025-11', 'import_monat_bis' => '2025-12'],
            SchuldModel::importMarkerAusGrund('forderung', 'getraenke', 'Getränkerechnung November–Dezember 2025')
        );

        // Nur Getränke-Forderungen bekommen Marker.
        $this->assertSame($keine, SchuldModel::importMarkerAusGrund('verbindlichkeit', 'getraenke', 'Getränkerechnung November 2025'));
        $this->assertSame($keine, SchuldModel::importMarkerAusGrund('forderung', 'sonstige', 'Getränkerechnung November 2025'));

        // Freitext, editierter Zusatz und der Beglichen-Marker parsen nicht.
        $this->assertSame($keine, SchuldModel::importMarkerAusGrund('forderung', 'getraenke', 'Bierkasten ausgelegt'));
        $this->assertSame($keine, SchuldModel::importMarkerAusGrund('forderung', 'getraenke', 'Getränkerechnung November 2025 (korrigiert)'));
        $this->assertSame($keine, SchuldModel::importMarkerAusGrund('forderung', 'getraenke', SchuldModel::GETRAENKE_BEGLICHEN_GRUND));
    }

    /**
     * DB-loser Test-Doppelgänger: überschreibt die beiden DB-Zugriffe (Lesen der
     * offenen Forderungen, Anlegen des Ausgleichs) und macht den ausgelagerten
     * Ausgleichs-Loop (ohne Transaktions-Boilerplate) öffentlich aufrufbar.
     * begleicheAlleGetraenke() selbst umschließt genau diesen Loop mit einer
     * Transaktion (db-abhängig, daher hier nicht direkt aufgerufen).
     */
    private function fakeModel(): SchuldModel
    {
        return new class extends SchuldModel {
            /** @var array<array{person: string, summe: string}> */
            public array $offene = [];
            /** @var array<array{person: string, betrag: float}> */
            public array $angelegt = [];

            public function getOffeneGetraenkeForderungen(): array
            {
                return $this->offene;
            }

            public function erstelleGetraenkeAusgleich(string $person, float $betrag): void
            {
                // Vorzeichen-Semantik wie im Original: Rückzahlung ist negativ.
                $this->angelegt[] = ['person' => $person, 'betrag' => -abs($betrag)];
            }

            public function loopOhneTransaktion(): int
            {
                return $this->erstelleAlleGetraenkeAusgleiche();
            }
        };
    }

    /**
     * Massen-Begleichen (Issue #55): legt je Person mit offener Getränke-
     * Forderung genau einen negativen Ausgleich über den vollen Betrag an, der
     * die offene Summe auf 0 bringt und als 1-Klick-Ausgleich erkannt wird.
     */
    public function testMassenBeglichenGleichtAllePersonenAus(): void
    {
        $model = $this->fakeModel();
        $model->offene = [
            ['person' => 'Achtzehn', 'summe' => '12.50'],
            ['person' => 'Kuhn', 'summe' => '3.00'],
            ['person' => 'Sobkowiak', 'summe' => '35.40'],
        ];

        $anzahl = $model->loopOhneTransaktion();

        $this->assertSame(3, $anzahl);
        $this->assertCount(3, $model->angelegt);

        foreach ($model->offene as $i => $offen) {
            $ausgleich = $model->angelegt[$i];
            $this->assertSame($offen['person'], $ausgleich['person']);
            // Ausgleich ist negativ und bringt die offene Summe auf 0.
            $this->assertLessThan(0, $ausgleich['betrag']);
            $this->assertEqualsWithDelta(0.0, (float) $offen['summe'] + $ausgleich['betrag'], 0.001);

            // Der angelegte Eintrag wird als 1-Klick-Ausgleich erkannt.
            $this->assertTrue(SchuldModel::istGetraenkeAusgleich([
                'typ' => 'forderung',
                'kategorie' => 'getraenke',
                'betrag' => (string) $ausgleich['betrag'],
                'grund' => SchuldModel::GETRAENKE_BEGLICHEN_GRUND,
                'beleg_id' => null,
                'buchung_id' => null,
                'abrechnung_id' => null,
            ]));
        }
    }

    public function testMassenBeglichenOhneOffeneForderungenLegtNichtsAn(): void
    {
        $model = $this->fakeModel();
        $model->offene = [];

        $this->assertSame(0, $model->loopOhneTransaktion());
        $this->assertSame([], $model->angelegt);
    }

    /**
     * Institutions-Guard (Issue #58): AH²-Bund/Heimverein sind keine Personen —
     * die E-Mail-UI der Detailseite bleibt für sie versteckt/gesperrt.
     */
    public function testIstInstitutionErkenntAbrechnungsInstitutionen(): void
    {
        $this->assertTrue(SchuldModel::istInstitution('AH²-Bund'));
        $this->assertTrue(SchuldModel::istInstitution('Heimverein'));
        // Matching wie überall über person_schluessel(): case-/whitespace-tolerant.
        $this->assertTrue(SchuldModel::istInstitution('  ah²-bund  '));
        $this->assertTrue(SchuldModel::istInstitution('HEIMVEREIN'));
    }

    public function testIstInstitutionLehntEchtePersonenAb(): void
    {
        $this->assertFalse(SchuldModel::istInstitution('Antek Sobkowiak'));
        $this->assertFalse(SchuldModel::istInstitution('Heimvereinsmeier'));
        $this->assertFalse(SchuldModel::istInstitution(''));
    }

    public function testInstitutionsNamenBleibenStabil(): void
    {
        // Regression-Pin: diese Strings stehen als schulden.person in der DB
        // (syncAbrechnungForderung) — Umbenennen würde Alt-Zeilen verwaisen.
        $this->assertSame('AH²-Bund', SchuldModel::INSTITUTION_PERSONEN['ah']);
        $this->assertSame('Heimverein', SchuldModel::INSTITUTION_PERSONEN['hv']);
    }
}
