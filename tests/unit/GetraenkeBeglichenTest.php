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
     * Massen-Begleichen (Issue #55): legt je Person mit offener Getränke-
     * Forderung genau einen negativen Ausgleich über den vollen Betrag an, der
     * die offene Summe auf 0 bringt und als 1-Klick-Ausgleich erkannt wird.
     *
     * DB-los getestet über die ausgelagerte Lese-Methode (Seam), damit der
     * Teststil (reine Unit-Tests ohne DB) erhalten bleibt.
     */
    public function testMassenBeglichenGleichtAllePersonenAus(): void
    {
        $model = new class extends SchuldModel {
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
        };

        $model->offene = [
            ['person' => 'Achtzehn', 'summe' => '12.50'],
            ['person' => 'Kuhn', 'summe' => '3.00'],
            ['person' => 'Sobkowiak', 'summe' => '35.40'],
        ];

        $anzahl = $model->begleicheAlleGetraenke();

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
        $model = new class extends SchuldModel {
            public array $angelegt = [];

            public function getOffeneGetraenkeForderungen(): array
            {
                return [];
            }

            public function erstelleGetraenkeAusgleich(string $person, float $betrag): void
            {
                $this->angelegt[] = [$person, $betrag];
            }
        };

        $this->assertSame(0, $model->begleicheAlleGetraenke());
        $this->assertSame([], $model->angelegt);
    }
}
