<?php

namespace App\Libraries;

/**
 * RechnungVersand - E-Mail-Versand der Getränkerechnungen (Issue #35)
 *
 * Dünner Wrapper um den CI4-Email-Service. SMTP kommt komplett aus den
 * email.*-Keys in .env (BaseConfig-Mapping auf Config\Email) — ohne
 * Konfiguration bleibt der Versand in der UI deaktiviert
 * (istKonfiguriert()-Gate), die Seite funktioniert trotzdem.
 */
class RechnungVersand
{
    /**
     * SMTP ist eingerichtet (Host, Absender und Protokoll gesetzt)?
     */
    public static function istKonfiguriert(): bool
    {
        $config = config('Email');

        return $config->protocol === 'smtp'
            && trim((string) $config->SMTPHost) !== ''
            && trim((string) $config->fromEmail) !== '';
    }

    /**
     * Verschickt eine einzelne Rechnung mit PDF-Anhang (aus dem Buffer,
     * keine Temp-Datei). Fehler landen im Log, nie als Exception in der UI.
     */
    public function sende(string $empfaenger, string $betreff, string $text, string $pdf, string $pdfName): bool
    {
        $email = service('email');
        $email->clear(true); // true = auch Anhänge des vorigen Sends verwerfen

        $email->setTo($empfaenger);
        $email->setSubject($betreff);
        $email->setMessage($text);
        // Mit gesetztem MIME-Type behandelt CI4 den ersten Parameter als Buffer
        $email->attach($pdf, 'attachment', $pdfName, 'application/pdf');

        if ($email->send()) {
            return true;
        }

        log_message('error', 'Rechnungsversand an {empfaenger} fehlgeschlagen: {debug}', [
            'empfaenger' => $empfaenger,
            'debug' => $email->printDebugger(['headers']),
        ]);

        return false;
    }

    /**
     * Betreff und Text der Einzelrechnungs-Mail.
     *
     * @return array{betreff: string, text: string}
     */
    public static function baueMail(string $person, string $monatsName, float $betrag): array
    {
        $kassenwart = trim((string) env('vdst.kassenwart_name', ''));
        $gruss = $kassenwart !== '' ? $kassenwart : 'der Kassenwart';

        $text = 'Hallo ' . $person . ',' . "\n\n"
            . 'anbei deine Getränkerechnung für ' . $monatsName
            . ' über ' . formatiere_betrag($betrag) . '.' . "\n"
            . 'Bitte überweise den Betrag zeitnah oder begleiche ihn direkt beim Kassenwart.' . "\n\n"
            . 'Viele Grüße' . "\n"
            . $gruss;

        return [
            'betreff' => 'Getränkerechnung ' . $monatsName . ' – VDSt zu Erlangen',
            'text' => $text,
        ];
    }
}
