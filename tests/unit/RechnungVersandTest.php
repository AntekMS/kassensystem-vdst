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
    /**
     * vdst.*-Keys, die baueMail() liest — vor/nach jedem Test isoliert, damit
     * weder ein reales .env noch Test-Reihenfolge das Ergebnis beeinflusst.
     */
    private const ENV_KEYS = [
        'vdst.kassenwart_name',
        'vdst.kassenwart_zeichen',
        'vdst.bank_kontoinhaber',
        'vdst.bank_iban',
        'vdst.bank_bic',
        'vdst.bank_name',
    ];

    private array $original = [];
    private array $originalEnv = [];
    private array $originalServer = [];

    protected function setUp(): void
    {
        parent::setUp();

        $config = config(Email::class);
        $this->original = [
            'protocol' => $config->protocol,
            'SMTPHost' => $config->SMTPHost,
            'fromEmail' => $config->fromEmail,
        ];

        // CI4 env() liest aus $_ENV UND $_SERVER — beide sichern/leeren, sonst
        // leaken reale .env-Werte oder Test-Reihenfolge in baueMail().
        foreach (self::ENV_KEYS as $key) {
            $this->originalEnv[$key] = $_ENV[$key] ?? null;
            $this->originalServer[$key] = $_SERVER[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        $config = config(Email::class);
        foreach ($this->original as $key => $wert) {
            $config->{$key} = $wert;
        }

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

    public function testBaueMailEnthaeltPersonMonatUndFrist(): void
    {
        // 2024-01-05 ist nachweislich ein Freitag (2024-01-01 war ein Montag).
        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        $this->assertSame('Getränkerechnung November 2025 – VDSt zu Erlangen', $mail['betreff']);
        $this->assertStringContainsString('Hallo Müller,', $mail['text']);
        $this->assertStringContainsString('November 2025', $mail['text']);
        $this->assertStringContainsString('Freitag, den 05.01.2024', $mail['text']);
    }

    public function testBaueMailZeigtBetragNichtMehrImText(): void
    {
        // Issue #60: der Betrag steht nur noch im PDF-Anhang, nicht im Mailtext.
        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        $this->assertStringNotContainsString('€', $mail['text']);
    }

    public function testBaueMailZeigtBankAbsatzMitIban(): void
    {
        $_ENV['vdst.bank_kontoinhaber'] = 'VDSt zu Erlangen';
        $_ENV['vdst.bank_iban'] = 'DE00 0000 0000 0000 0000 00';
        $_ENV['vdst.bank_bic'] = 'ABCDEFGHXXX';
        $_ENV['vdst.bank_name'] = 'Musterbank Erlangen';

        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        $this->assertStringContainsString('Falls du kein Lastschriftmandat erteilt hast', $mail['text']);
        $this->assertStringContainsString('VDSt zu Erlangen', $mail['text']);
        $this->assertStringContainsString('IBAN: DE00 0000 0000 0000 0000 00', $mail['text']);
        $this->assertStringContainsString('BIC: ABCDEFGHXXX', $mail['text']);
        $this->assertStringContainsString('Musterbank Erlangen', $mail['text']);
        $this->assertStringContainsString('Verwendungszweck: Getränke November 2025 + dein Name', $mail['text']);
    }

    public function testBaueMailMitIbanAberOhneBicLaesstBicZeileWeg(): void
    {
        $_ENV['vdst.bank_kontoinhaber'] = 'VDSt zu Erlangen';
        $_ENV['vdst.bank_iban'] = 'DE00 0000 0000 0000 0000 00';
        // BIC und Bankname leer — dürfen keine kaputten Zeilen erzeugen.
        $_ENV['vdst.bank_bic'] = '';
        $_ENV['vdst.bank_name'] = '';

        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        // IBAN und Verwendungszweck bleiben, BIC-Zeile fehlt komplett.
        $this->assertStringContainsString('IBAN: DE00 0000 0000 0000 0000 00', $mail['text']);
        $this->assertStringContainsString('Verwendungszweck: Getränke November 2025 + dein Name', $mail['text']);
        $this->assertStringNotContainsString('BIC', $mail['text']);
        // Keine Leerzeile innerhalb des Bank-Blocks (doppelter Umbruch).
        $this->assertStringNotContainsString(
            "IBAN: DE00 0000 0000 0000 0000 00\n\n",
            $mail['text']
        );
    }

    public function testBaueMailOhneIbanZeigtKeinenBankAbsatz(): void
    {
        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        $this->assertStringNotContainsString('Falls du kein Lastschriftmandat erteilt hast', $mail['text']);
        $this->assertStringNotContainsString('IBAN', $mail['text']);
    }

    public function testBaueMailSignaturMitVerbindungszeichen(): void
    {
        $_ENV['vdst.kassenwart_name'] = 'Max Mustermann';
        $_ENV['vdst.kassenwart_zeichen'] = 'V!!!';

        $mail = RechnungVersand::baueMail('Müller', 'November 2025', '2024-01-05');

        $this->assertStringContainsString("Max Mustermann\nKassenwart V!!!", $mail['text']);
    }
}
