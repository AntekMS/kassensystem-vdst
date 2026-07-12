<?php

use App\Libraries\RechnungVersand;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email;

/**
 * Testet das SMTP-Konfigurations-Gate und den Mailtext des
 * Rechnungsversands (Issue #35). DB-frei.
 *
 * @internal
 */
final class RechnungVersandTest extends CIUnitTestCase
{
    private array $original = [];

    protected function setUp(): void
    {
        parent::setUp();

        $config = config(Email::class);
        $this->original = [
            'protocol' => $config->protocol,
            'SMTPHost' => $config->SMTPHost,
            'fromEmail' => $config->fromEmail,
        ];
    }

    protected function tearDown(): void
    {
        $config = config(Email::class);
        foreach ($this->original as $key => $wert) {
            $config->{$key} = $wert;
        }

        parent::tearDown();
    }

    private function konfiguriere(string $protocol, string $host, string $from): void
    {
        $config = config(Email::class);
        $config->protocol = $protocol;
        $config->SMTPHost = $host;
        $config->fromEmail = $from;
    }

    public function testIstKonfiguriertMitVollstaendigemSmtp(): void
    {
        $this->konfiguriere('smtp', 'mail.example.org', 'kassenwart@example.org');

        $this->assertTrue(RechnungVersand::istKonfiguriert());
    }

    public function testNichtKonfiguriertOhneHost(): void
    {
        $this->konfiguriere('smtp', '  ', 'kassenwart@example.org');

        $this->assertFalse(RechnungVersand::istKonfiguriert());
    }

    public function testNichtKonfiguriertOhneAbsender(): void
    {
        $this->konfiguriere('smtp', 'mail.example.org', '');

        $this->assertFalse(RechnungVersand::istKonfiguriert());
    }

    public function testNichtKonfiguriertMitMailProtokoll(): void
    {
        $this->konfiguriere('mail', 'mail.example.org', 'kassenwart@example.org');

        $this->assertFalse(RechnungVersand::istKonfiguriert());
    }

    public function testBaueMailEnthaeltPersonMonatUndBetrag(): void
    {
        $mail = RechnungVersand::baueMail('Müller', 'November 2025', 12.5);

        $this->assertSame('Getränkerechnung November 2025 – VDSt zu Erlangen', $mail['betreff']);
        $this->assertStringContainsString('Hallo Müller,', $mail['text']);
        $this->assertStringContainsString('November 2025', $mail['text']);
        $this->assertStringContainsString('12,50 €', $mail['text']);
    }
}
