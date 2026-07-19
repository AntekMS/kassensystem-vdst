<?php

use App\Models\SchuldPositionModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die puren, DB-losen Seams der Getränkedetails (Issue #63, #59b):
 * - summePasst(): der Guard, der entscheidet, ob die PDF-Rechnung die
 *   Einzelpositionen ausweist oder auf die reine Gesamtsumme zurückfällt
 *   (nachträglich editierte Forderungen!).
 * - fuegeZusammen(): Merge gleicher Positionen über mehrere schulden-Zeilen
 *   derselben Person (erlaubter Doppelimport).
 *
 * @internal
 */
final class SchuldPositionTest extends CIUnitTestCase
{
    public function testSummePasstBeiUebereinstimmung(): void
    {
        $positionen = [
            ['summe' => 2.6],
            ['summe' => '11.00'], // DB liefert DECIMAL als String
            ['summe' => 9.0],
        ];

        $this->assertTrue(SchuldPositionModel::summePasst($positionen, 22.6));
        // Float-Rauschen gecachter Formelwerte bleibt in der Toleranz
        $this->assertTrue(SchuldPositionModel::summePasst($positionen, 22.600000000000004));
    }

    public function testSummePasstNichtBeiAbweichungOderLeererListe(): void
    {
        $this->assertFalse(SchuldPositionModel::summePasst([['summe' => 2.6]], 22.6));
        // Editierter Betrag → Details dürfen nicht mehr erscheinen
        $this->assertFalse(SchuldPositionModel::summePasst([['summe' => 22.6]], 20.0));
        // Leere Liste ist nie „passend" — es gibt nichts zu rendern
        $this->assertFalse(SchuldPositionModel::summePasst([], 0.0));
    }

    public function testFuegeZusammenAddiertGleichePositionen(): void
    {
        $zusammen = SchuldPositionModel::fuegeZusammen([
            ['bezeichnung' => 'Biere', 'anzahl' => '2.00', 'einzelpreis' => '1.30', 'summe' => '2.60'],
            ['bezeichnung' => 'Spalter', 'anzahl' => 3, 'einzelpreis' => 1, 'summe' => 3],
            ['bezeichnung' => 'Biere', 'anzahl' => 1, 'einzelpreis' => 1.3, 'summe' => 1.3],
        ]);

        $this->assertSame([
            ['bezeichnung' => 'Biere', 'anzahl' => 3.0, 'einzelpreis' => 1.3, 'summe' => 3.9],
            ['bezeichnung' => 'Spalter', 'anzahl' => 3.0, 'einzelpreis' => 1.0, 'summe' => 3.0],
        ], $zusammen);
    }

    public function testFuegeZusammenTrenntUnterschiedlicheEinzelpreise(): void
    {
        // Preisänderung zwischen zwei Importen: gleiche Bezeichnung, anderer
        // Preis → zwei Zeilen, damit Anzahl × Einzelpreis = Summe stimmt.
        $zusammen = SchuldPositionModel::fuegeZusammen([
            ['bezeichnung' => 'Biere', 'anzahl' => 2, 'einzelpreis' => 1.3, 'summe' => 2.6],
            ['bezeichnung' => 'Biere', 'anzahl' => 2, 'einzelpreis' => 1.5, 'summe' => 3.0],
        ]);

        $this->assertCount(2, $zusammen);
    }
}
