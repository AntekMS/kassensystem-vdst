<?php

namespace App\Database\Migrations;

use App\Libraries\GetraenkeRechnungImport;
use App\Models\SchuldModel;
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
 * abgeleitet (GetraenkeRechnungImport::parseMonatsName). Nicht parsebare Gründe
 * bleiben NULL — die tauchten auch bisher nicht im Versand auf (grund weicht ab).
 */
class SchuldenImportMonat extends Migration
{
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
     * aus ihrem grund ab (ein UPDATE je unterschiedlichem grund).
     */
    private function migriereBestandsdaten(): void
    {
        $praefix = 'Getränkerechnung ';

        $gruende = $this->db->table('schulden')
            ->distinct()
            ->select('grund')
            ->where('typ', 'forderung')
            ->where('kategorie', 'getraenke')
            ->like('grund', $praefix, 'after')
            ->where('grund !=', SchuldModel::GETRAENKE_BEGLICHEN_GRUND)
            ->get()
            ->getResultArray();

        foreach ($gruende as $zeile) {
            $zeitraum = GetraenkeRechnungImport::parseMonatsName(
                substr($zeile['grund'], strlen($praefix))
            );

            if ($zeitraum === null) {
                continue;
            }

            $this->db->table('schulden')
                ->where('typ', 'forderung')
                ->where('kategorie', 'getraenke')
                ->where('grund', $zeile['grund'])
                ->update([
                    'import_monat' => $zeitraum['von'],
                    'import_monat_bis' => $zeitraum['bis'],
                ]);
        }
    }
}
