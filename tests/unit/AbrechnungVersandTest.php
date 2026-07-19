<?php

use App\Commands\AbrechnungenVersenden;
use App\Libraries\RechnungVersand;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet den automatischen Abrechnungs-Monatsversand (Issue #37): den
 * Mailtext (baueAbrechnungMail) und den Skip/Send-Seam (sollVersenden). DB-frei.
 *
 * @internal
 */
final class AbrechnungVersandTest extends CIUnitTestCase
{
    /** vdst.*-Keys, die baueAbrechnungMail() liest — pro Test isoliert. */
    private const ENV_KEYS = [
        'vdst.kassenwart_name',
        'vdst.kassenwart_zeichen',
    ];

    private array $originalEnv = [];
    private array $originalServer = [];

    protected function setUp(): void
    {
        parent::setUp();

        // CI4 env() liest aus $_ENV UND $_SERVER — beide sichern/leeren, sonst
        // leaken reale .env-Werte oder Test-Reihenfolge in den Mailtext.
        foreach (self::ENV_KEYS as $key) {
            $this->originalEnv[$key] = $_ENV[$key] ?? null;
            $this->originalServer[$key] = $_SERVER[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            if ($this->originalEnv[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->originalEnv[$key];
            }

            if ($this->originalServer[$key] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $this->originalServer[$key];
            }
        }

        parent::tearDown();
    }

    public function testBaueAbrechnungMailEnthaeltTypUndMonat(): void
    {
        $mail = RechnungVersand::baueAbrechnungMail('AH²', 'Juni 2024');

        $this->assertSame('AH²-Abrechnung Juni 2024 – automatischer Monatsexport', $mail['betreff']);
        $this->assertStringContainsString('AH²-Abrechnung für Juni 2024', $mail['text']);
        $this->assertStringContainsString('Excel plus alle Belege als ZIP', $mail['text']);
        // Der Status-Hinweis (bleibt unverändert) gehört zum Kern der #37-Semantik.
        $this->assertStringContainsString('bleibt unverändert', $mail['text']);
    }

    public function testBaueAbrechnungMailSignaturMitVerbindungszeichen(): void
    {
        $_ENV['vdst.kassenwart_name'] = 'Max Mustermann';
        $_ENV['vdst.kassenwart_zeichen'] = 'V!!!';

        $mail = RechnungVersand::baueAbrechnungMail('Heimverein', 'Juni 2024');

        $this->assertStringContainsString('Hallo Max Mustermann,', $mail['text']);
        $this->assertStringContainsString("Max Mustermann\nKassenwart V!!!", $mail['text']);
    }

    public function testBaueAbrechnungMailOhneZeichenEndetSchlichtMitKassenwart(): void
    {
        $_ENV['vdst.kassenwart_name'] = 'Max Mustermann';
        // Leeres Zeichen explizit setzen — env() nutzt den ($_ENV-)Leerwert, bevor
        // es auf getenv() (ggf. real gesetztes .env) durchfällt → deterministisch.
        $_ENV['vdst.kassenwart_zeichen'] = '';

        $mail = RechnungVersand::baueAbrechnungMail('Heimverein', 'Juni 2024');

        $this->assertStringContainsString("Max Mustermann\nKassenwart", $mail['text']);
        // Ohne Zeichen endet die Signatur (und damit die Mail) schlicht mit "Kassenwart".
        $this->assertStringEndsWith('Kassenwart', $mail['text']);
    }

    public function testSollVersendenNurBeiAbrechnungMitBelegen(): void
    {
        $abrechnung = ['id' => 1, 'abrechnungsmonat' => '2024-06'];
        $belege = [['id' => 5]];

        $this->assertTrue(AbrechnungenVersenden::sollVersenden($abrechnung, $belege));
    }

    public function testSollVersendenFalseOhneAbrechnung(): void
    {
        $this->assertFalse(AbrechnungenVersenden::sollVersenden(null, []));
        $this->assertFalse(AbrechnungenVersenden::sollVersenden(null, [['id' => 5]]));
    }

    public function testSollVersendenFalseOhneBelege(): void
    {
        $abrechnung = ['id' => 1, 'abrechnungsmonat' => '2024-06'];

        $this->assertFalse(AbrechnungenVersenden::sollVersenden($abrechnung, []));
    }
}
