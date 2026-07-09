<?php

/**
 * Label-Helper - Zentrale Übersetzung von DB-Werten in Anzeige-Labels
 *
 * Wird über app/Config/Autoload.php automatisch geladen und ist damit
 * in Controllern, Models und Views verfügbar.
 */

if (!function_exists('kategorie_optionen')) {
    /**
     * @return array<string, string>
     */
    function kategorie_optionen(): array
    {
        return [
            'normal' => 'Normal',
            'ah_berechtigt' => 'AH² berechtigt',
            'hv_berechtigt' => 'HV berechtigt',
        ];
    }
}

if (!function_exists('kategorie_label')) {
    function kategorie_label(?string $kategorie): string
    {
        return kategorie_optionen()[$kategorie] ?? (string) $kategorie;
    }
}

if (!function_exists('beleg_status_optionen')) {
    /**
     * @return array<string, string>
     */
    function beleg_status_optionen(): array
    {
        return [
            'erfasst' => 'Erfasst',
            'in_abrechnung' => 'In Abrechnung',
            'abgerechnet' => 'Abgerechnet',
            'bezahlt' => 'Bezahlt',
        ];
    }
}

if (!function_exists('beleg_status_label')) {
    function beleg_status_label(?string $status): string
    {
        return beleg_status_optionen()[$status] ?? (string) $status;
    }
}

if (!function_exists('abrechnung_status_label')) {
    function abrechnung_status_label(?string $status): string
    {
        $labels = [
            'entwurf' => 'Entwurf',
            'ausstehend' => 'Ausstehend',
            'eingereicht' => 'Eingereicht',
            'bezahlt' => 'Bezahlt',
        ];

        return $labels[$status] ?? (string) $status;
    }
}

if (!function_exists('konto_optionen')) {
    /**
     * @return array<string, string>
     */
    function konto_optionen(): array
    {
        return [
            'aktivenkasse' => 'Aktivenkasse',
            'getraenkekasse' => 'Getränkekasse',
            'barkasse' => 'Barkasse',
        ];
    }
}

if (!function_exists('konto_label')) {
    function konto_label(?string $konto): string
    {
        return konto_optionen()[$konto] ?? (string) $konto;
    }
}

if (!function_exists('buchungsart_label')) {
    function buchungsart_label(?string $buchungsart): string
    {
        return $buchungsart === 'einnahme' ? 'Einnahme' : 'Ausgabe';
    }
}

if (!function_exists('formatiere_betrag')) {
    /**
     * Formatiert einen Betrag für die Anzeige (deutsches Format).
     */
    function formatiere_betrag($betrag): string
    {
        return number_format((float) $betrag, 2, ',', '.') . ' €';
    }
}

if (!function_exists('normalisiere_betrag')) {
    /**
     * Normalisiert deutsche Betrag-Eingaben für die Validierung.
     *
     * "10,50" → "10.50", "1.234,56" → "1234.56", "1.000" → "1000";
     * ein einzelner Punkt als Dezimaltrenner bleibt erhalten ("10.50" → "10.50",
     * "1.5" → "1.5"). Der Punkt wird nur dann als Tausendertrenner entfernt,
     * wenn die Eingabe wie Tausendergruppen aussieht (z.B. "1.000", "1.234.567").
     */
    function normalisiere_betrag(?string $eingabe): ?string
    {
        if ($eingabe === null) {
            return null;
        }

        $eingabe = trim($eingabe);

        if (str_contains($eingabe, ',')) {
            // Komma = Dezimaltrenner, Punkt(e) = Tausendertrenner
            $eingabe = str_replace('.', '', $eingabe);
            $eingabe = str_replace(',', '.', $eingabe);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $eingabe)) {
            // Nur Punkte in Tausendergruppen ("1.000", "1.234.567") → Tausendertrenner
            $eingabe = str_replace('.', '', $eingabe);
        }

        return $eingabe;
    }
}
