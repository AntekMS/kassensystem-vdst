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

    public function testEinzelRechnungMitPositionenLiefertPdf(): void
    {
        // Getränkedetails (Issue #63): Positionssumme = Betrag → Tabelle wird gerendert
        $bytes = $this->pdf->einzel('Müller', 'März 2026', 12.5, '2026-03-31', [
            ['bezeichnung' => 'Biere', 'anzahl' => 5, 'einzelpreis' => 1.3, 'summe' => 6.5],
            ['bezeichnung' => 'Spalter', 'anzahl' => 6, 'einzelpreis' => 1.0, 'summe' => 6.0],
        ]);

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    /**
     * Der Guard der Getränkedetails: Positionen erscheinen nur, wenn ihre
     * Summe den autoritativen Rechnungsbetrag trifft — sonst leere Liste
     * (das Template fällt dann auf die reine Gesamtsumme zurück).
     */
    public function testPositionenFuerVerwirftUnpassendeSummen(): void
    {
        $positionen = [['bezeichnung' => 'Biere', 'anzahl' => 5, 'einzelpreis' => 1.3, 'summe' => 6.5]];

        $this->assertSame($positionen, RechnungPdf::positionenFuer($positionen, 6.5));
        $this->assertSame([], RechnungPdf::positionenFuer($positionen, 12.5));
        $this->assertSame([], RechnungPdf::positionenFuer([], 0.0));
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

    public function testColeurBundMitPositionenLiefertPdf(): void
    {
        $bytes = $this->pdf->coleurBund('Bund', 'November 2025', 33.0, '2025-11-30', [
            ['bezeichnung' => 'Biere', 'anzahl' => 10, 'einzelpreis' => 1.3, 'summe' => 13.0],
            ['bezeichnung' => 'Turmherren', 'anzahl' => 40, 'einzelpreis' => 0.5, 'summe' => 20.0],
        ]);

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAbrechnungLiefertPdf(): void
    {
        // Issue #83: AH²-Monatsabrechnung als PDF-Rechnung (Belegliste + Summe).
        $abrechnung = ['titel' => 'AH² Abrechnung Juni 2024', 'gesamtsumme' => 42.5];
        $belege = [
            ['beschreibung' => 'Getränke Café Müller', 'rechnungsdatum' => '2024-06-15', 'belegnummer' => '2024-06-15-001', 'betrag' => 30.0, 'lieferant' => 'Café Müller'],
            ['beschreibung' => 'Büromaterial', 'rechnungsdatum' => '2024-06-20', 'belegnummer' => '2024-06-20-002', 'betrag' => 12.5, 'lieferant' => ''],
        ];

        $bytes = $this->pdf->abrechnung('AH²', 'Juni 2024', $abrechnung, $belege, '2024-06-30');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testAbrechnungMitBegruendungUndOhneBelegeLiefertPdf(): void
    {
        // HV-Abrechnung mit Freitext-Begründung, leere Belegliste (Randfall).
        $abrechnung = ['titel' => 'HV Abrechnung März 2026', 'gesamtsumme' => 0.0, 'begruendung' => 'Renovierung Küche – Anteil Heimverein.'];

        $bytes = $this->pdf->abrechnung('HV', 'März 2026', $abrechnung, [], '2026-03-31');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testInventurLiefertPdf(): void
    {
        $kontostaende = [
            'barkasse' => ['saldo' => 120.5],
            'aktivenkasse' => ['saldo' => 340.0],
        ];
        $inventur = [
            'forderung' => ['getraenke' => 80.0, 'abrechnung' => 0.0, 'sonstige' => 0.0, 'summe' => 80.0],
            'verbindlichkeit' => ['getraenke' => 0.0, 'abrechnung' => 0.0, 'sonstige' => 15.0, 'summe' => 15.0],
        ];

        $bytes = $this->pdf->inventur($kontostaende, $inventur, 460.5, 525.5, '2026-07-18');

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function testInventurDateinameFormat(): void
    {
        $this->assertSame('Inventur_2026-07-18.pdf', RechnungPdf::inventurDateiname('2026-07-18'));
    }
}
