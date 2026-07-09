<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet normalisiere_betrag() aus dem label_helper — insbesondere den
 * Tausenderpunkt-Fall (#29), bei dem "1.000" fälschlich als "1.00" gespeichert wurde.
 *
 * @internal
 */
final class BetragTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    /**
     * @dataProvider betragProvider
     */
    public function testNormalisiereBetrag(?string $eingabe, ?string $erwartet): void
    {
        $this->assertSame($erwartet, normalisiere_betrag($eingabe));
    }

    public static function betragProvider(): array
    {
        return [
            'deutsches Komma'                 => ['10,50', '10.50'],
            'Tausenderpunkt mit Komma'        => ['1.234,56', '1234.56'],
            'Tausenderpunkt ohne Komma'       => ['1.000', '1000'],
            'mehrere Tausendergruppen'        => ['1.234.567', '1234567'],
            'Punkt als Dezimaltrenner (2 NK)' => ['10.50', '10.50'],
            'Punkt als Dezimaltrenner (1 NK)' => ['1.5', '1.5'],
            'ganze Zahl'                      => ['1000', '1000'],
            'mit Whitespace'                  => ['  42,00  ', '42.00'],
            'null bleibt null'                => [null, null],
            'leerer String'                   => ['', ''],
        ];
    }
}
