<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Personen-Tabelle (Issue #61 — Fundament für #59)
 *
 * Führt eine echte Personen-/Mitglieder-Tabelle ein und löst damit die bisher
 * reine Freitext-Repräsentation ab. Bewusste Architektur-Entscheidung
 * (Registry + Soft-Link):
 * - `persons` ist das autoritative Namens-/E-Mail-Register (vorname/nachname/email).
 * - `schulden.person_id` ist ein NULLABLE Soft-Link (ON DELETE SET NULL). Der
 *   `schulden.person`-String bleibt als denormalisierter Anzeige-/Gruppierungs-
 *   schlüssel bestehen (Institutions-Zeilen „AH²-Bund"/„Heimverein" und Gäste
 *   haben person_id NULL und leben weiter nur über den String).
 * - E-Mails wandern von der eigenen Tabelle `person_emails` in `persons.email`;
 *   `person_emails` wird danach entfernt.
 *
 * Cross-Tabellen-Matching läuft weiterhin ausschließlich über person_schluessel()
 * (whitespace-/case-normalisiert) — jetzt zentral über PersonModel.
 *
 * ROLLBACK: down() stellt `person_emails` nur strukturell (leer) wieder her —
 * die migrierten E-Mail-Zuordnungen gehen dabei verloren.
 */
class CreatePersonenTabelle extends Migration
{
    public function up()
    {
        // 1) persons anlegen
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'vorname' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
            ],
            'nachname' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'comment' => 'Pflicht; Legacy-Freitext landet komplett hier (#62 splittet Vor-/Nachname)',
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '254',
                'null' => true,
            ],
            'aktiv' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
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
        // Kein UNIQUE auf nachname: Namensgleichheit ist der #62-Mehrdeutigkeitsfall.
        $this->forge->addKey('nachname');
        $this->forge->createTable('persons', true);

        // 2) Soft-Link schulden.person_id
        $this->forge->addColumn('schulden', [
            'person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
                'after' => 'person',
            ],
        ]);
        $this->db->query('ALTER TABLE schulden ADD CONSTRAINT fk_schulden_person
            FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE SET NULL');
        $this->db->query('CREATE INDEX idx_schulden_person ON schulden (person_id)');

        // 3) Bestandsdaten migrieren
        $this->migriereBestandsdaten();

        // 4) person_emails entfernen (E-Mails jetzt in persons.email)
        $this->forge->dropTable('person_emails', true);
    }

    /**
     * Baut aus den bestehenden Freitext-Namen echte persons-Einträge und
     * verknüpft die schulden-Zeilen per Soft-Link.
     *
     * - Kandidaten: schulden.person (nur echte Personen, abrechnung_id IS NULL —
     *   schließt die Institutions-Forderungen AH²-Bund/Heimverein aus) plus alle
     *   person_emails.name.
     * - Dedupe case-/whitespace-insensitiv über person_schluessel().
     * - E-Mail je Schlüssel aus person_emails übernommen.
     * - schulden.person_id anhand desselben Schlüssels backfillen.
     */
    private function migriereBestandsdaten(): void
    {
        // E-Mail-Map: schluessel => email
        $emailMap = [];
        foreach ($this->db->table('person_emails')->select('name, email')->get()->getResultArray() as $zeile) {
            $emailMap[person_schluessel($zeile['name'])] = $zeile['email'];
        }

        // Kandidatennamen sammeln (erste gesehene, normalisierte Schreibweise gewinnt)
        $namen = [];
        $schuldNamen = $this->db->table('schulden')
            ->select('person')
            ->where('abrechnung_id', null)
            ->distinct()
            ->get()->getResultArray();
        foreach ($schuldNamen as $zeile) {
            $this->merkeName($namen, $zeile['person']);
        }
        // Namen aus person_emails ebenfalls berücksichtigen (auch wenn keine Schuld existiert)
        foreach ($this->db->table('person_emails')->select('name')->get()->getResultArray() as $zeile) {
            $this->merkeName($namen, $zeile['name']);
        }

        // persons anlegen und schluessel => id merken
        $idMap = [];
        foreach ($namen as $schluessel => $nachname) {
            $this->db->table('persons')->insert([
                'nachname' => $nachname,
                'email' => $emailMap[$schluessel] ?? null,
                'aktiv' => 1,
            ]);
            $idMap[$schluessel] = (int) $this->db->insertID();
        }

        // schulden.person_id backfillen. Automatische Abrechnungs-Zeilen
        // (abrechnung_id gesetzt = Institutions-Forderung AH²-Bund/Heimverein)
        // bleiben bewusst NULL — konsistent zu syncAbrechnungForderung, auch wenn
        // zufällig eine gleichnamige manuelle Person existiert.
        $schuldZeilen = $this->db->table('schulden')
            ->select('id, person')
            ->where('abrechnung_id', null)
            ->get()->getResultArray();
        foreach ($schuldZeilen as $zeile) {
            $schluessel = person_schluessel($zeile['person']);
            if (isset($idMap[$schluessel])) {
                $this->db->table('schulden')->where('id', $zeile['id'])
                    ->update(['person_id' => $idMap[$schluessel]]);
            }
        }
    }

    /**
     * Merkt einen Namen unter seinem person_schluessel; erste Schreibweise gewinnt.
     */
    private function merkeName(array &$namen, string $roh): void
    {
        $name = person_normalisiere($roh);
        if ($name === '') {
            return;
        }
        $schluessel = person_schluessel($name);
        if (!isset($namen[$schluessel])) {
            $namen[$schluessel] = $name;
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE schulden DROP FOREIGN KEY fk_schulden_person');
        $this->db->query('DROP INDEX idx_schulden_person ON schulden');
        $this->forge->dropColumn('schulden', 'person_id');
        $this->forge->dropTable('persons', true);

        // person_emails strukturell (leer) wiederherstellen
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
    }
}
