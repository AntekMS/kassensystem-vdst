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
}
