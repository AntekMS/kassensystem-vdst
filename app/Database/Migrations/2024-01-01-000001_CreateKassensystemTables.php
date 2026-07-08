<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * VDStE Kassensystem - Database Migration
 *
 * Erstellt alle Tabellen für das Kassensystem
 * Ausführen mit: php spark migrate
 */
class CreateKassensystemTables extends Migration
{
    public function up()
    {
        // =====================================================
        // KERN-TABELLE: Belege
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'belegnummer' => [
                'type' => 'VARCHAR',
                'constraint' => '15',
                'unique' => true,
                'comment' => 'Format: YYYY-MM-DD-001'
            ],
            'rechnungsdatum' => [
                'type' => 'DATE',
                'comment' => 'Datum auf der Rechnung (für Belegnummer)'
            ],
            'eingabedatum' => [
                'type' => 'DATE',
                'comment' => 'Wann du es eingegeben hast'
            ],
            'beschreibung' => [
                'type' => 'TEXT',
            ],
            'betrag' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'lieferant' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'dateiname_original' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'comment' => 'Ursprünglicher Upload-Name'
            ],
            'dateiname_system' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'comment' => 'Umbenannt zu Belegnummer'
            ],
            'dateipfad' => [
                'type' => 'VARCHAR',
                'constraint' => '500',
                'comment' => 'Vollständiger Pfad zur Datei'
            ],
            'dateityp' => [
                'type' => 'ENUM',
                'constraint' => ['pdf', 'jpg', 'jpeg', 'png'],
            ],
            'dateigroesse' => [
                'type' => 'INT',
                'comment' => 'in Bytes'
            ],
            'kategorie' => [
                'type' => 'ENUM',
                'constraint' => ['normal', 'ah_berechtigt', 'hv_berechtigt'],
                'default' => 'normal'
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['erfasst', 'in_abrechnung', 'abgerechnet', 'bezahlt'],
                'default' => 'erfasst'
            ],
            'notizen' => [
                'type' => 'TEXT',
                'null' => true,
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
        // belegnummer already has a UNIQUE index from 'unique' => true above;
        // a plain addKey('belegnummer') here would create a duplicate index name.
        $this->forge->addKey('rechnungsdatum');
        $this->forge->addKey('status');
        $this->forge->addKey('kategorie');
        $this->forge->createTable('belege', true);

        // =====================================================
        // Buchungen (Kassenbuch)
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'beleg_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'comment' => 'Optional: Verknüpfung zu Beleg'
            ],
            'buchungsdatum' => [
                'type' => 'DATE',
                'comment' => 'Wann gebucht (kann != Rechnungsdatum)'
            ],
            'beschreibung' => [
                'type' => 'TEXT',
                'comment' => 'Buchungstext (falls abweichend von Beleg)'
            ],
            'betrag' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'konto_typ' => [
                'type' => 'ENUM',
                'constraint' => ['aktivenkasse', 'getraenkekasse', 'barkasse'],
            ],
            'buchungsart' => [
                'type' => 'ENUM',
                'constraint' => ['ausgabe', 'einnahme'],
            ],
            'notizen' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey('buchungsdatum');
        $this->forge->addKey('konto_typ');
        $this->forge->addKey('buchungsart');
        $this->forge->addForeignKey('beleg_id', 'belege', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('buchungen', true);

        // =====================================================
        // AH² Abrechnungen
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'abrechnungsmonat' => [
                'type' => 'VARCHAR',
                'constraint' => '7',
                'comment' => 'Format: YYYY-MM'
            ],
            'titel' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'comment' => 'z.B. "AH² Abrechnung Juni 2024"'
            ],
            'erstellt_am' => [
                'type' => 'DATE',
            ],
            'eingereicht_am' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'bezahlt_am' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['entwurf', 'ausstehend', 'eingereicht', 'bezahlt'],
                'default' => 'entwurf'
            ],
            'gesamtsumme' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'comment' => 'Wird automatisch berechnet'
            ],
            'notizen' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey('abrechnungsmonat');
        $this->forge->addKey('status');
        $this->forge->addKey('erstellt_am');
        $this->forge->createTable('ah_abrechnungen', true);

        // =====================================================
        // HV Abrechnungen
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'abrechnungsmonat' => [
                'type' => 'VARCHAR',
                'constraint' => '7',
                'comment' => 'Format: YYYY-MM'
            ],
            'titel' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'comment' => 'z.B. "HV Abrechnung Juni 2024 - Hausrenovierung"'
            ],
            'erstellt_am' => [
                'type' => 'DATE',
            ],
            'eingereicht_am' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'bezahlt_am' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['entwurf', 'ausstehend', 'eingereicht', 'bezahlt'],
                'default' => 'entwurf'
            ],
            'gesamtsumme' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'comment' => 'Wird automatisch berechnet'
            ],
            'begruendung' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Begründung für HV-Erstattung'
            ],
            'notizen' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey('abrechnungsmonat');
        $this->forge->addKey('status');
        $this->forge->addKey('erstellt_am');
        $this->forge->createTable('hv_abrechnungen', true);

        // =====================================================
        // Verknüpfung: Belege <-> Abrechnungen
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'beleg_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'abrechnung_typ' => [
                'type' => 'ENUM',
                'constraint' => ['ah', 'hv'],
            ],
            'abrechnung_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'hinzugefuegt_am' => [
                'type' => 'TIMESTAMP',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['beleg_id', 'abrechnung_typ', 'abrechnung_id'], 'unique_beleg_abrechnung');
        $this->forge->addKey(['abrechnung_typ', 'abrechnung_id']);
        $this->forge->addKey('beleg_id');
        $this->forge->addForeignKey('beleg_id', 'belege', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('abrechnung_belege', true);

        // =====================================================
        // System-Einstellungen
        // =====================================================
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'schluessel' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'unique' => true,
            ],
            'wert' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'beschreibung' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('system_einstellungen', true);
    }

    public function down()
    {
        // Tabellen in umgekehrter Reihenfolge löschen (wegen Foreign Keys)
        $this->forge->dropTable('abrechnung_belege', true);
        $this->forge->dropTable('buchungen', true);
        $this->forge->dropTable('hv_abrechnungen', true);
        $this->forge->dropTable('ah_abrechnungen', true);
        $this->forge->dropTable('belege', true);
        $this->forge->dropTable('system_einstellungen', true);
    }
}