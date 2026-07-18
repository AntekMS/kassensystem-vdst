<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Zeitraum-Schlüssel für das Versand-Log (Issue #64-Nachtrag)
 *
 * Seit Issue #57/#64 sind Einzelmonat ("2025-11") und Zeitraum
 * ("2025-11"–"2025-12") getrennte Import-Identitäten — das Versand-Log
 * (getraenke_versand) war aber nur auf den Startmonat gekeyt. Folge: ein
 * bereits verschickter November-Einzelversand markierte auf der
 * Nov–Dez-Zeitraum-Versandseite alle Personen fälschlich als „verschickt"
 * (Checkbox default abgewählt) — und lud zum Überspringen des Batches ein.
 *
 * monat_bis spiegelt die Marker-Semantik von schulden.import_monat_bis:
 * Endmonat bei Zeitraum-Versand, NULL bei Einzelmonat (bis === von wird
 * vor dem Insert normalisiert). Alt-Logs bleiben NULL und zählen damit
 * als Einzelmonat-Versand — Zeitraum-Versände vor dieser Migration
 * verlieren ihr (rein informatives) Badge.
 */
class GetraenkeVersandMonatBis extends Migration
{
    public function up()
    {
        $this->forge->addColumn('getraenke_versand', [
            'monat_bis' => [
                'type' => 'CHAR',
                'constraint' => 7,
                'null' => true,
                'after' => 'monat',
                'comment' => 'JJJJ-MM Endmonat bei Zeitraum-Versand, sonst NULL',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('getraenke_versand', 'monat_bis');
    }
}
