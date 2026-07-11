<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Schulden-Verknüpfung (Issue #38)
 *
 * Verbindet die Schuldenliste mit ihren Quellen, damit Einträge automatisch
 * gepflegt werden können statt alles manuell zu erfassen:
 * - belege.erstattung_person: wem der Verein den Beleg erstatten muss
 *   (Freitext wie schulden.person; leer = keine Erstattung nötig)
 * - schulden.beleg_id/buchung_id: FK mit ON DELETE CASCADE — wird die Quelle
 *   gelöscht, verschwindet der automatische Eintrag mit
 * - schulden.abrechnung_typ + abrechnung_id: Verweis auf ah_/hv_abrechnungen
 *   (zwei Tabellen, daher kein FK; Abrechnungen sind ohnehin nur als
 *   entwurf/ausstehend löschbar, wo keine verknüpften Einträge existieren)
 */
class SchuldenVerknuepfung extends Migration
{
    public function up()
    {
        $this->forge->addColumn('belege', [
            'erstattung_person' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
                'after' => 'lieferant',
                'comment' => 'Freitext-Name: wem der Verein den Beleg erstattet',
            ],
        ]);

        $this->forge->addColumn('schulden', [
            'beleg_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'betrag',
            ],
            'buchung_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'beleg_id',
            ],
            'abrechnung_typ' => [
                'type' => 'ENUM',
                'constraint' => ['ah', 'hv'],
                'null' => true,
                'after' => 'buchung_id',
            ],
            'abrechnung_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'abrechnung_typ',
            ],
        ]);

        $this->db->query('ALTER TABLE schulden ADD CONSTRAINT fk_schulden_beleg
            FOREIGN KEY (beleg_id) REFERENCES belege(id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE schulden ADD CONSTRAINT fk_schulden_buchung
            FOREIGN KEY (buchung_id) REFERENCES buchungen(id) ON DELETE CASCADE');
        $this->db->query('CREATE INDEX idx_schulden_abrechnung
            ON schulden (abrechnung_typ, abrechnung_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE schulden DROP FOREIGN KEY fk_schulden_beleg');
        $this->db->query('ALTER TABLE schulden DROP FOREIGN KEY fk_schulden_buchung');
        $this->db->query('DROP INDEX idx_schulden_abrechnung ON schulden');

        $this->forge->dropColumn('schulden', ['beleg_id', 'buchung_id', 'abrechnung_typ', 'abrechnung_id']);
        $this->forge->dropColumn('belege', 'erstattung_person');
    }
}
