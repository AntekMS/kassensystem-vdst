<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die Schulden-Labels aus dem label_helper und die
 * Getränkestopp-Konstante (Issue #27).
 *
 * @internal
 */
final class SchuldLabelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    public function testSchuldTypLabels(): void
    {
        $this->assertSame('Forderung', schuld_typ_label('forderung'));
        $this->assertSame('Verbindlichkeit', schuld_typ_label('verbindlichkeit'));
        // Unbekannte Werte fallen auf den Rohwert zurück
        $this->assertSame('unbekannt', schuld_typ_label('unbekannt'));
        $this->assertSame('', schuld_typ_label(null));
    }

    public function testSchuldKategorieLabels(): void
    {
        $this->assertSame('Getränke', schuld_kategorie_label('getraenke'));
        $this->assertSame('Abrechnung', schuld_kategorie_label('abrechnung'));
        $this->assertSame('Sonstige', schuld_kategorie_label('sonstige'));
        $this->assertSame('unbekannt', schuld_kategorie_label('unbekannt'));
    }

    public function testOptionenDeckenAlleEnumWerteAb(): void
    {
        $this->assertSame(['forderung', 'verbindlichkeit'], array_keys(schuld_typ_optionen()));
        $this->assertSame(['getraenke', 'abrechnung', 'sonstige'], array_keys(schuld_kategorie_optionen()));
    }

    public function testGetraenkestoppLimit(): void
    {
        $this->assertTrue(defined('GETRAENKESTOPP_LIMIT'));
        $this->assertSame(50.00, GETRAENKESTOPP_LIMIT);
    }
}
