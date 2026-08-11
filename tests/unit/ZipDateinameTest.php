<?php

namespace Tests\Unit;

use App\Helpers\ZipHelper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Dateinamen-Bausteine des ZIP-Exports (Issue #91).
 *
 * Die Sanitize-Regel lag vorher in drei Kopien (ZipHelper, BelegeController,
 * BuchungenController) und unterschied sich nur im Längenlimit — diese Tests
 * pinnen das jetzt gemeinsame Verhalten.
 */
final class ZipDateinameTest extends CIUnitTestCase
{
    // --- dateinameTeil() ----------------------------------------------------

    public function testEntferntSonderzeichen(): void
    {
        // Slash/Raute/Ausrufezeichen fallen weg, Umlaute und Ziffern bleiben.
        $this->assertSame('GetränkeRechnung_5', ZipHelper::dateinameTeil('Getränke/Rechnung #5!', 40));
    }

    public function testBehaeltUmlauteUndZiffern(): void
    {
        // Umlaute stehen bewusst in der Whitelist des Sanitizers.
        $this->assertSame('Bierfässer_2025', ZipHelper::dateinameTeil('Bierfässer 2025', 40));
    }

    public function testFasstWhitespaceZusammen(): void
    {
        $this->assertSame('A_B_C', ZipHelper::dateinameTeil("  A   B \n C  ", 40));
    }

    public function testKuerztAufMaxLenOhneRandUnterstrich(): void
    {
        // Nach dem Kürzen darf kein Unterstrich am Ende stehen bleiben.
        $this->assertSame('Eins_Zwei', ZipHelper::dateinameTeil('Eins Zwei Drei', 10));
        $this->assertSame('Eins', ZipHelper::dateinameTeil('Eins Zwei Drei', 5));
    }

    public function testLeererTextBleibtLeer(): void
    {
        $this->assertSame('', ZipHelper::dateinameTeil('', 40));
        $this->assertSame('', ZipHelper::dateinameTeil(null, 40));
        $this->assertSame('', ZipHelper::dateinameTeil('###', 40));
    }

    // --- belegDateiname() ---------------------------------------------------

    private function beleg(array $overrides = []): array
    {
        return array_merge([
            'belegnummer' => '2025-11-04-001',
            'beschreibung' => 'Getränke Coleur',
            'dateityp' => 'pdf',
        ], $overrides);
    }

    public function testBelegDateinameFormat(): void
    {
        $this->assertSame(
            '01_2025-11-04-001_Getränke_Coleur.pdf',
            ZipHelper::belegDateiname($this->beleg(), 1)
        );
    }

    public function testBelegDateinamePolstertLaufendeNummer(): void
    {
        $this->assertSame('09', substr(ZipHelper::belegDateiname($this->beleg(), 9), 0, 2));
        // Dreistellig wird nicht abgeschnitten
        $this->assertSame('123', substr(ZipHelper::belegDateiname($this->beleg(), 123), 0, 3));
    }

    public function testBelegDateinameOhneBeschreibung(): void
    {
        // Kein Trenner-Unterstrich vor der Endung, wenn nichts übrig bleibt.
        $this->assertSame(
            '01_2025-11-04-001.pdf',
            ZipHelper::belegDateiname($this->beleg(['beschreibung' => '###']), 1)
        );
    }

    public function testBelegDateinameRespektiertMaxLen(): void
    {
        $beleg = $this->beleg(['beschreibung' => 'Eine sehr lange Beschreibung die gekürzt gehört']);

        $lang = ZipHelper::belegDateiname($beleg, 1, 40);
        $kurz = ZipHelper::belegDateiname($beleg, 1, 20);

        $this->assertNotSame($lang, $kurz);
        $this->assertStringStartsWith('01_2025-11-04-001_Eine_sehr_lange_', $lang);
        // Hart auf 20 Zeichen gekürzt — mitten im Wort, wie schon vor #91.
        $this->assertSame('01_2025-11-04-001_Eine_sehr_lange_Besc.pdf', $kurz);
    }
}
