<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * E-Mail-Versand der Getränkerechnungen (Issue #35)
 *
 * person_emails: Zuordnung Freitext-Name → E-Mail-Adresse (bewusst kein FK,
 * Personen in schulden sind Freitext; UNIQUE über die Default-Kollation
 * macht den Namen case-insensitiv eindeutig).
 *
 * getraenke_versand: Log der verschickten Einzelrechnungen — zeigt auf der
 * Versand-Seite "verschickt am" an und schützt vor versehentlichem
 * Doppelversand (bewusst keine harte Sperre).
 */
class CreatePersonEmailsUndVersandLog extends Migration
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
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'comment' => 'Freitext-Name wie in schulden.person',
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '254',
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
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('person_emails', true);

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'monat' => [
                'type' => 'CHAR',
                'constraint' => '7',
                'comment' => 'JJJJ-MM der Getränkerechnung',
            ],
            'person' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '254',
                'comment' => 'Adresse, an die tatsächlich geschickt wurde',
            ],
            'gesendet_am' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['monat', 'person']);
        $this->forge->createTable('getraenke_versand', true);
    }

    public function down()
    {
        $this->forge->dropTable('getraenke_versand', true);
        $this->forge->dropTable('person_emails', true);
    }
}
