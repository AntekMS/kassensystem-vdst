<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Schuldenliste (Issue #27)
 *
 * Ledger für Forderungen (Person schuldet dem Verein) und Verbindlichkeiten
 * (Verein schuldet der Person). Person ist bewusst Freitext — keine eigene
 * Personen-Tabelle. Rückzahlungen werden als negativer Betrag erfasst.
 */
class CreateSchuldenTable extends Migration
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
            'person' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'comment' => 'Freitext-Name, Gruppierung case-insensitiv über Kollation',
            ],
            'typ' => [
                'type' => 'ENUM',
                'constraint' => ['forderung', 'verbindlichkeit'],
                'default' => 'forderung',
            ],
            'kategorie' => [
                'type' => 'ENUM',
                'constraint' => ['getraenke', 'abrechnung', 'sonstige'],
                'default' => 'getraenke',
            ],
            'datum' => [
                'type' => 'DATE',
            ],
            'grund' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'comment' => 'z.B. "Getränkerechnung April 2025"',
            ],
            'betrag' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'comment' => 'negativ = Rückzahlung',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('person');
        $this->forge->addKey(['typ', 'kategorie']);
        $this->forge->createTable('schulden', true);
    }

    public function down()
    {
        $this->forge->dropTable('schulden', true);
    }
}
