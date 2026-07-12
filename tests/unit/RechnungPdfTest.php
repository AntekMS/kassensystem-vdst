<?php

use App\Libraries\RechnungPdf;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die PDF-Rechnungen (Issue #35): Dateinamen-Slug und Smoke-Tests,
 * dass alle drei Template-Varianten (mit Umlauten und €) ein gültiges
 * PDF liefern.
 *
 * @internal
 */
final class RechnungPdfTest extends CIUnitTestCase
{
    private RechnungPdf $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdf = new RechnungPdf();
    }

    public function testDateinameTransliteriertUmlaute(): void
    {
        $this->assertSame(
            'Getraenkerechnung_November_2025_Mueller.pdf',
            RechnungPdf::dateiname('November 2025', 'Müller')
        );
    }

    public function testDateinameEntferntSonderzeichen(): void
    {
        $this->assertSame(
            'Getraenkerechnung_Maerz_2026_Ossig-Grosse__Gast.pdf',
            RechnungPdf::dateiname('März 2026', 'Oßig-Große / (Gast)!')
        );
    }

    public function testDateinameNormalisiertLeerraum(): void
    {
        $this->assertSame(
            'Getraenkerechnung_Juli_2026_Uebersicht.pdf',
            RechnungPdf::dateiname('  Juli   2026 ', 'Übersicht')
        );
    }

    public function testEinzelRechnungLiefertPdf(): void
    {
        $bytes = $this->pdf->einzel('Müller-Lüdenscheidt', 'März 2026', 12.5, '2026-03-31');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testUebersichtLiefertPdf(): void
    {
        $bytes = $this->pdf->uebersicht('November 2025', [
            ['person' => 'Müller', 'betrag' => 10.5],
            ['person' => 'Große', 'betrag' => '3.20'],
        ], '2025-11-30');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testColeurBundLiefertPdf(): void
    {
        $bytes = $this->pdf->coleurBund('Coleur', 'November 2025', 47.8, '2025-11-30');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
