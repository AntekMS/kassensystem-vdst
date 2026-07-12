<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Erweitert belege.dateityp um 'xlsx'.
 *
 * Der Getränkerechnung-Import (Issue #35) legt die hochgeladene Excel-Datei
 * als Beleg-Datei für die Coleur-/Bund-Belege ab.
 */
class ErweitereBelegDateitypXlsx extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE belege MODIFY dateityp ENUM('pdf','jpg','jpeg','png','xlsx') NOT NULL"
        );
    }

    public function down()
    {
        // Achtung: xlsx-Belege müssen vor dem Rollback gelöscht sein,
        // sonst schlägt der ALTER an bestehenden Zeilen fehl.
        $this->db->query(
            "ALTER TABLE belege MODIFY dateityp ENUM('pdf','jpg','jpeg','png') NOT NULL"
        );
    }
}
