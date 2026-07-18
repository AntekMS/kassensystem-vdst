<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Typisierter Import-Marker für Getränke-Forderungen (Issue #64)
 *
 * Die importierten Getränke-Forderungen wurden bisher allein über den
 * editierbaren Freitext-grund ("Getränkerechnung November 2025") identifiziert —
 * ein Tippfehler ließ die Person lautlos aus Versand-Seite/Übersichts-PDF
 * verschwinden. Analog zu den Quell-Spalten aus Issue #38 bekommen die Einträge
 * jetzt typisierte Marker-Spalten:
 * - import_monat "JJJJ-MM" (wie getraenke_versand.monat)
 * - import_monat_bis "JJJJ-MM" für Zeiträume (Issue #57); bei Einzelmonat NULL
 *   (bis === von wird wie in monatsName() zu NULL normalisiert)
 * Der grund bleibt reiner Anzeige-/Editier-Text.
 *
 * Backfill: bestehende Import-Forderungen bekommen import_monat aus dem grund
 * abgeleitet. Nicht parsebare Gründe bleiben NULL — die tauchten auch bisher
 * nicht im Versand auf (grund weicht ab).
 *
 * Die Migration ist bewusst SELBSTSTÄNDIG (eingefrorene Kopie des
 * Reverse-Parsers statt App-Klassen): Migrationen laufen auf jedem
 * Fresh-Install/Restore erneut und dürfen nicht an lebendem Code hängen,
 * der sich später ändern kann.
 */
class SchuldenImportMonat extends Migration
{
    private const PRAEFIX = 'Getränkerechnung ';

    private const MONATSNAMEN = [
        '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
        '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember',
    ];

    public function up()
    {
        $this->forge->addColumn('schulden', [
            'import_monat' => [
                'type' => 'CHAR',
                'constraint' => 7,
                'null' => true,
                'after' => 'abrechnung_id',
                'comment' => 'JJJJ-MM des Getränke-Imports (Startmonat)',
            ],
            'import_monat_bis' => [
                'type' => 'CHAR',
                'constraint' => 7,
                'null' => true,
                'after' => 'import_monat',
                'comment' => 'JJJJ-MM Endmonat bei Zeitraum-Import, sonst NULL',
            ],
        ]);

        $this->db->query('CREATE INDEX idx_schulden_import_monat
            ON schulden (import_monat)');

        $this->migriereBestandsdaten();
    }

    public function down()
    {
        $this->db->query('DROP INDEX idx_schulden_import_monat ON schulden');

        $this->forge->dropColumn('schulden', ['import_monat', 'import_monat_bis']);
    }

    /**
     * Leitet import_monat/import_monat_bis der Bestands-Forderungen einmalig
     * aus ihrem grund ab — pro Zeile, damit das Ergebnis deterministisch ist:
     * Die DB-Kollation (utf8mb4_general_ci) matcht case-/akzent-insensitiv,
     * der Parser unten ist byte-exakt; ein DISTINCT über den grund würde
     * kollations-gleiche Varianten zu EINEM unspezifizierten Repräsentanten
     * kollabieren und je nach Zufall auch korrekt geschriebene Zeilen
     * überspringen. Der strncmp-Guard hält beide Welten auseinander.
     */
    private function migriereBestandsdaten(): void
    {
        $zeilen = $this->db->table('schulden')
            ->select('id, grund')
            ->where('typ', 'forderung')
            ->where('kategorie', 'getraenke')
            ->like('grund', self::PRAEFIX, 'after')
            ->get()
            ->getResultArray();

        foreach ($zeilen as $zeile) {
            // LIKE oben matcht kollations-insensitiv — nur byte-exakte
            // Präfixe parsen (alles andere fand auch der alte exakte
            // grund-Match der Versand-Seite nicht).
            if (strncmp($zeile['grund'], self::PRAEFIX, strlen(self::PRAEFIX)) !== 0) {
                continue;
            }

            $zeitraum = $this->parseMonatsName(
                substr($zeile['grund'], strlen(self::PRAEFIX))
            );

            if ($zeitraum === null) {
                continue;
            }

            $this->db->table('schulden')
                ->where('id', $zeile['id'])
                ->update([
                    'import_monat' => $zeitraum['von'],
                    'import_monat_bis' => $zeitraum['bis'],
                ]);
        }
    }

    /**
     * Eingefrorene Kopie von GetraenkeRechnungImport::parseMonatsName()
     * (Stand dieser Migration): "November 2025", "November–Dezember 2025"
     * oder "November 2025–Januar 2026" → von/bis als "YYYY-MM", sonst null.
     * "Getränkerechnung beglichen" (Issue #43) fällt hier automatisch durch.
     * bis === von wird zu NULL normalisiert (Einzelmonat-Semantik).
     *
     * @return ?array{von: string, bis: ?string}
     */
    private function parseMonatsName(string $name): ?array
    {
        $teile = explode('–', trim($name), 2);

        $bis = null;
        if (isset($teile[1])) {
            $bis = $this->parseEinzelMonatsName(trim($teile[1]));
            if ($bis === null) {
                return null;
            }
        }

        $vonTeil = trim($teile[0]);
        if ($bis !== null && array_search($vonTeil, self::MONATSNAMEN, true) !== false) {
            $vonTeil .= ' ' . substr($bis, 0, 4);
        }

        $von = $this->parseEinzelMonatsName($vonTeil);
        if ($von === null || ($bis !== null && $bis < $von)) {
            return null;
        }

        return ['von' => $von, 'bis' => $bis === $von ? null : $bis];
    }

    /**
     * "November 2025" → "2025-11", sonst null.
     */
    private function parseEinzelMonatsName(string $name): ?string
    {
        if (preg_match('/^(\p{L}+) (\d{4})$/u', $name, $treffer) !== 1) {
            return null;
        }

        $nummer = array_search($treffer[1], self::MONATSNAMEN, true);

        return $nummer === false ? null : $treffer[2] . '-' . $nummer;
    }
}
