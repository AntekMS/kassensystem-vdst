<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Positions-Tabelle für die Getränkedetails (Issue #63, #59b)
 *
 * Der Getränke-Import speichert je importierter Forderung die Einzelpositionen
 * (Menge je Getränk × Einzelpreis) aus der Excel des Getränkewarts — damit die
 * Einzelrechnung (PDF-Versand, jederzeit erneut aufrufbar) die Details
 * ausweisen kann. Die Positionen sind reine ANZEIGE-Daten: der Forderungsbetrag
 * bleibt autoritativ in schulden.betrag; passt die Positionssumme nicht mehr
 * dazu (z.B. nachträglich editierter Betrag), fällt das PDF auf die reine
 * Gesamtsumme zurück.
 *
 * Löschen der Forderung räumt per FK-Cascade auf. Coleur-/Bund-Positionen
 * werden NICHT gespeichert — deren PDF-Beleg entsteht direkt beim Import.
 */
class CreateSchuldPositionen extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'schuld_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'bezeichnung' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'anzahl' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
            ],
            'einzelpreis' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'summe' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'sortierung' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('schuld_id');
        $this->forge->createTable('schuld_positionen', true);

        $this->db->query('ALTER TABLE schuld_positionen ADD CONSTRAINT fk_schuld_positionen_schuld
            FOREIGN KEY (schuld_id) REFERENCES schulden(id) ON DELETE CASCADE');
    }

    public function down()
    {
        $this->forge->dropTable('schuld_positionen', true);
    }
}
