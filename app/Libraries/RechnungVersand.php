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
     * Deutsche Wochentagsnamen, indiziert über date('N') (1 = Montag … 7 = Sonntag).
     * Bewusst als eigenes Mapping statt intl/setlocale (Verein hat kein
     * garantiertes de_DE-Locale auf dem Server).
     */
    private const WOCHENTAGE = [
        1 => 'Montag',
        2 => 'Dienstag',
        3 => 'Mittwoch',
        4 => 'Donnerstag',
        5 => 'Freitag',
        6 => 'Samstag',
        7 => 'Sonntag',
    ];

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
     * Verschickt eine einzelne Nachricht mit Datei-Anhang (aus dem Buffer,
     * keine Temp-Datei). Fehler landen im Log, nie als Exception in der UI.
     * $mime steuert den Anhang-Typ — Default PDF (Einzelrechnung, Issue #35);
     * der Abrechnungs-Auto-Versand (Issue #37) reicht 'application/zip' durch.
     */
    public function sende(string $empfaenger, string $betreff, string $text, string $anhang, string $anhangName, string $mime = 'application/pdf'): bool
    {
        $email = service('email');
        $email->clear(true); // true = auch Anhänge des vorigen Sends verwerfen

        $email->setTo($empfaenger);
        $email->setSubject($betreff);
        $email->setMessage($text);
        // Mit gesetztem MIME-Type behandelt CI4 den ersten Parameter als Buffer
        $email->attach($anhang, 'attachment', $anhangName, $mime);

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
     * Betreff und Text der Einzelrechnungs-Mail (Issue #60). Der Betrag steht
     * bewusst NUR im PDF-Anhang, nicht mehr im Mailtext. $fristDatum kommt als
     * 'Y-m-d' rein (vom Aufrufer bereits validiert) und wird als
     * "{Wochentag}, den {d.m.Y}" ausgegeben.
     *
     * @return array{betreff: string, text: string}
     */
    public static function baueMail(string $person, string $monatsName, string $fristDatum): array
    {
        $kassenwart = trim((string) env('vdst.kassenwart_name', ''));
        $gruss = $kassenwart !== '' ? $kassenwart : 'der Kassenwart';
        $zeichen = trim((string) env('vdst.kassenwart_zeichen', ''));

        $frist = \DateTime::createFromFormat('Y-m-d', $fristDatum) ?: new \DateTime();
        $fristText = self::WOCHENTAGE[(int) $frist->format('N')] . ', den ' . $frist->format('d.m.Y');

        $absaetze = [
            'Hallo ' . $person . ',',
            'die Getränkerechnung für ' . $monatsName . ' ist da.',
            'Ich werde Ende dieser Woche die fälligen Beträge per Lastschrift einziehen (siehe Anlage).',
            'Bitte beachte Folgendes:',
            'Falls dein Konto nicht ausreichend gedeckt ist, gib mir bitte bis spätestens ' . $fristText
                . ', kurz Bescheid, damit wir Rücklastschriftgebühren vermeiden können.',
        ];

        // Überweisungs-Absatz nur, wenn eine IBAN hinterlegt ist — ohne
        // Bank-Konfiguration bleibt es beim Lastschrift-Hinweis oben.
        // Bankdaten aus der gemeinsamen Quelle (bank_daten(), Issue #96).
        $bank = bank_daten();

        if ($bank['konfiguriert']) {
            // Jede Zeile nur bei nicht-leerem Wert — sonst entstünden "BIC: "
            // oder Leerzeilen in der Mail. Verwendungszweck hängt nicht an
            // BIC/Name und bleibt daher immer stehen.
            $bankZeilen = array_filter([
                $bank['kontoinhaber'],
                'IBAN: ' . $bank['iban'],
                $bank['bic'] !== '' ? 'BIC: ' . $bank['bic'] : '',
                $bank['bankname'],
                'Verwendungszweck: Getränke ' . $monatsName . ' + dein Name',
            ], static fn (string $zeile): bool => $zeile !== '');

            $absaetze[] = 'Falls du kein Lastschriftmandat erteilt hast, überweise den Betrag bitte manuell auf folgendes Konto:';
            $absaetze[] = implode("\n", $bankZeilen);
        }

        $absaetze[] = 'Bei Fragen stehe ich dir gerne zur Verfügung.';
        $absaetze[] = 'Mit freundlichen Grüßen,' . "\n"
            . $gruss . "\n"
            . 'Kassenwart' . ($zeichen !== '' ? ' ' . $zeichen : '');

        return [
            'betreff' => 'Getränkerechnung ' . $monatsName . ' – VDSt zu Erlangen',
            'text' => implode("\n\n", $absaetze),
        ];
    }

    /**
     * Betreff und Text der automatischen Abrechnungs-Monatsmail (Issue #37).
     * Geht an den Kassenwart selbst als fertiges Paket zum Prüfen/Weiterleiten —
     * bewusst sachlich, ohne Frist-/Bank-Absätze. Rein und testbar.
     *
     * @param string $typName    Anzeigename der Abrechnung ("AH²", "Heimverein")
     * @param string $monatsName Monat im Klartext (z.B. "Juni 2026")
     * @return array{betreff: string, text: string}
     */
    public static function baueAbrechnungMail(string $typName, string $monatsName): array
    {
        $kassenwart = trim((string) env('vdst.kassenwart_name', ''));
        $gruss = $kassenwart !== '' ? $kassenwart : 'der Kassenwart';
        $zeichen = trim((string) env('vdst.kassenwart_zeichen', ''));

        $absaetze = [
            'Hallo ' . $gruss . ',',
            'im Anhang findest du die offene ' . $typName . '-Abrechnung für ' . $monatsName
                . ' (Excel plus alle Belege als ZIP) — automatisch erzeugt zum Monatsende.',
            'Bitte prüfe die Abrechnung und leite sie an den zuständigen Verantwortlichen weiter. '
                . 'Der Status in der Anwendung bleibt unverändert; setze ihn dort wie gewohnt selbst auf „eingereicht".',
            'Mit freundlichen Grüßen,' . "\n"
                . $gruss . "\n"
                . 'Kassenwart' . ($zeichen !== '' ? ' ' . $zeichen : ''),
        ];

        return [
            'betreff' => $typName . '-Abrechnung ' . $monatsName . ' – automatischer Monatsexport',
            'text' => implode("\n\n", $absaetze),
        ];
    }
}
