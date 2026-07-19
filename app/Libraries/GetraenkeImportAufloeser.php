<?php

namespace App\Libraries;

use App\Models\PersonModel;

/**
 * Löst die Nachnamen des Getränke-Imports gegen das Personen-Register auf
 * (Issue #62, #59a). Reiner, DB-loser Resolver: bekommt die geparsten Namen,
 * die Nachname-Map (aus PersonModel::nachnameMap()) und optional die im
 * Vorschau-Formular getroffenen Wahlen — gibt pro Eintrag den zu speichernden
 * Anzeigenamen + person_id zurück.
 *
 * Matching läuft über person_schluessel() (whitespace-/case-normalisiert),
 * konsistent zum restlichen Personen-Matching. SICHERHEIT: person_id-Wahlen aus
 * dem POST werden hier gegen echte Personen validiert (mehrdeutig: nur unter den
 * Kandidaten; unbekannt: gegen alle Personen), nie ungeprüft übernommen.
 */
class GetraenkeImportAufloeser
{
    /**
     * @param array<array{person: string, betrag: float, positionen?: array}> $personen  Parser-Output
     * @param array<string, array<array>>                 $nachnameMap  schluessel(nachname) => Personen
     * @param array<string, ?int>                         $wahlen       schluessel(Nachname) => gewählte person_id
     * @return array<array{roh: string, betrag: float, positionen: array, status: string, kandidaten: array<array>, person: string, person_id: ?int}>
     */
    public static function loese(array $personen, array $nachnameMap, array $wahlen = []): array
    {
        // Flache id => Person-Map über ALLE Register-Personen (für die
        // Validierung der „unbekannt"-Wahlen gegen beliebige Personen).
        $alleNachId = [];
        foreach ($nachnameMap as $liste) {
            foreach ($liste as $person) {
                $alleNachId[(int) $person['id']] = $person;
            }
        }

        $ergebnis = [];
        foreach ($personen as $eintrag) {
            $roh = person_normalisiere((string) $eintrag['person']);
            $schluessel = person_schluessel($roh);
            $kandidaten = $nachnameMap[$schluessel] ?? [];
            $wahl = $wahlen[$schluessel] ?? null;

            $anzahl = count($kandidaten);
            if ($anzahl === 1) {
                $status = 'eindeutig';
                $person = $kandidaten[0];
            } elseif ($anzahl >= 2) {
                $status = 'mehrdeutig';
                $person = self::waehleAusKandidaten($kandidaten, $wahl);
            } else {
                $status = 'unbekannt';
                $person = ($wahl !== null && isset($alleNachId[$wahl])) ? $alleNachId[$wahl] : null;
            }

            $ergebnis[] = [
                'roh' => $roh,
                'betrag' => (float) $eintrag['betrag'],
                // Getränkedetails (Issue #63) unverändert durchreichen — sie
                // hängen am Eintrag, nicht an der aufgelösten Person.
                'positionen' => $eintrag['positionen'] ?? [],
                'status' => $status,
                'kandidaten' => $kandidaten,
                // Aufgelöst → Anzeigename der Person; sonst roher Nachname (Gast).
                'person' => $person ? PersonModel::anzeigename($person) : $roh,
                'person_id' => $person ? (int) $person['id'] : null,
            ];
        }

        return $ergebnis;
    }

    /**
     * Gibt es Einträge, die nicht eindeutig aufgelöst sind (mehrdeutig/unbekannt)
     * — für den Vorschau-Hinweis „bitte zuordnen".
     *
     * @param array<array{status: string}> $aufgeloest
     */
    public static function brauchtAuswahl(array $aufgeloest): bool
    {
        foreach ($aufgeloest as $eintrag) {
            if ($eintrag['status'] !== 'eindeutig') {
                return true;
            }
        }

        return false;
    }

    /**
     * Wählt aus den Kandidaten (gleicher Nachname) die per POST gewählte Person —
     * nur gültig, wenn die id tatsächlich unter den Kandidaten ist.
     *
     * @param array<array> $kandidaten
     */
    private static function waehleAusKandidaten(array $kandidaten, ?int $wahl): ?array
    {
        if ($wahl === null) {
            return null;
        }
        foreach ($kandidaten as $kandidat) {
            if ((int) $kandidat['id'] === $wahl) {
                return $kandidat;
            }
        }

        return null;
    }
}
