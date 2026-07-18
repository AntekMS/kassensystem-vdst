<?php

/**
 * Label-Helper - Zentrale Übersetzung von DB-Werten in Anzeige-Labels
 *
 * Wird über app/Config/Autoload.php automatisch geladen und ist damit
 * in Controllern, Models und Views verfügbar.
 */

if (!function_exists('person_normalisiere')) {
    /**
     * Trimmt einen Freitext-Personennamen und kollabiert Mehrfach-Whitespace
     * auf ein Leerzeichen. Muss auf Schreib- UND Lesepfad identisch angewandt
     * werden, sonst finden sich Namen mit Doppel-Leerzeichen nicht wieder.
     */
    function person_normalisiere(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }
}

if (!function_exists('person_schluessel')) {
    /**
     * Kanonischer Vergleichs-Schlüssel für einen Freitext-Personennamen
     * (whitespace-normalisiert + lowercase). EINZIGE Quelle für das
     * case-insensitive Matching zwischen schulden.person, dem Personen-Register
     * (persons via PersonModel) und getraenke_versand — nicht ad-hoc
     * mb_strtolower() daneben bauen.
     *
     * Seit Issue #61 vereinheitlicht PersonModel das Matching auf genau diesen
     * Schlüssel (anzeigename → person_schluessel): Soft-Link (person_id) und
     * E-Mail-Auflösung laufen darüber, statt über die frühere accent-insensitive
     * DB-Kollation von person_emails. Dieser Schlüssel ist NICHT accent-insensitiv
     * ('Müller' != 'Muller') — bewusst, um Namensdubletten nicht zu verschmelzen.
     */
    function person_schluessel(string $name): string
    {
        return mb_strtolower(person_normalisiere($name));
    }
}

if (!function_exists('person_anker')) {
    /**
     * HTML-/URL-sicherer Anker für eine Personenzeile (Issue #55).
     *
     * Scroll-Ziel nach den 1-Klick-Getränkeaktionen: der Controller hängt
     * `#` . person_anker($name) an das Redirect-Ziel, die Übersicht setzt
     * dieselbe id auf die `<tr>`. Baut bewusst auf person_schluessel() auf
     * (kein eigenes Slug-Schema), damit Fragment und id garantiert identisch
     * sind; Nicht-[a-z0-9] (Leerzeichen, Umlaute, Klammern) werden auf `-`
     * reduziert, damit die id/Fragment gültig bleibt.
     */
    function person_anker(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', person_schluessel($name));

        return 'person-' . trim((string) $slug, '-');
    }
}

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

if (!function_exists('schuld_typ_optionen')) {
    /**
     * @return array<string, string>
     */
    function schuld_typ_optionen(): array
    {
        return [
            'forderung' => 'Forderung',
            'verbindlichkeit' => 'Verbindlichkeit',
        ];
    }
}

if (!function_exists('schuld_typ_label')) {
    function schuld_typ_label(?string $typ): string
    {
        return schuld_typ_optionen()[$typ] ?? (string) $typ;
    }
}

if (!function_exists('schuld_kategorie_optionen')) {
    /**
     * @return array<string, string>
     */
    function schuld_kategorie_optionen(): array
    {
        return [
            'getraenke' => 'Getränke',
            'abrechnung' => 'Abrechnung',
            'sonstige' => 'Sonstige',
        ];
    }
}

if (!function_exists('schuld_kategorie_label')) {
    function schuld_kategorie_label(?string $kategorie): string
    {
        return schuld_kategorie_optionen()[$kategorie] ?? (string) $kategorie;
    }
}

if (!function_exists('beleg_status_badge_class')) {
    /**
     * CSS-Klassen für den Soft-Status-Badge eines Belegs (Design-System app.css).
     */
    function beleg_status_badge_class(?string $status): string
    {
        $klassen = [
            'erfasst' => 'badge-status-neutral',
            'in_abrechnung' => 'badge-status-rot',
            'abgerechnet' => 'badge-status-amber',
            'bezahlt' => 'badge-status-gruen',
        ];

        return 'badge-status ' . ($klassen[$status] ?? 'badge-status-neutral');
    }
}

if (!function_exists('abrechnung_status_badge_class')) {
    /**
     * CSS-Klassen für den Soft-Status-Badge einer Abrechnung (Design-System app.css).
     */
    function abrechnung_status_badge_class(?string $status): string
    {
        $klassen = [
            'entwurf' => 'badge-status-neutral',
            'ausstehend' => 'badge-status-amber',
            'eingereicht' => 'badge-status-rot',
            'bezahlt' => 'badge-status-gruen',
        ];

        return 'badge-status ' . ($klassen[$status] ?? 'badge-status-neutral');
    }
}

if (!function_exists('kategorie_badge_class')) {
    /**
     * CSS-Klassen für den Kategorie-Badge eines Belegs: abrechnungsfähige
     * Kategorien (AH²/HV) bekommen den umrandeten Badge, Normales bleibt neutral.
     */
    function kategorie_badge_class(?string $kategorie): string
    {
        return 'badge-status ' . ($kategorie === 'normal' || $kategorie === null
            ? 'badge-status-neutral'
            : 'badge-status-outline');
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

if (!function_exists('schaetze_archiv_groesse')) {
    /**
     * Schätzt die Gesamtgröße eines Beleg-Archivs für die Vorschau.
     * Nutzt die tatsächliche Dateigröße, sonst eine Schätzung nach Dateityp.
     */
    function schaetze_archiv_groesse(array $belege): string
    {
        $gesamtgroesse = 0;

        foreach ($belege as $beleg) {
            if (isset($beleg['dateigroesse']) && $beleg['dateigroesse'] > 0) {
                $gesamtgroesse += $beleg['dateigroesse'];
            } else {
                $gesamtgroesse += ($beleg['dateityp'] ?? '') === 'pdf' ? 200000 : 500000;
            }
        }

        if ($gesamtgroesse < 1024 * 1024) {
            return number_format($gesamtgroesse / 1024, 0) . ' KB';
        }

        return number_format($gesamtgroesse / (1024 * 1024), 1) . ' MB';
    }
}
