<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Entfernt ungenutzte Datenbank-Objekte:
 *
 * - Die Views v_belege_mit_buchung und v_abrechnungen_overview werden
 *   nirgends im Code verwendet.
 * - Die drei Trigger duplizierten die Gesamtsummen-Berechnung, die bereits
 *   in PHP läuft (Ah/HvAbrechnungModel::berechneGesamtsumme wird bei jedem
 *   Hinzufügen/Entfernen aufgerufen). Eine Implementierung statt zwei.
 * - Die Tabelle system_einstellungen wird nirgends gelesen; Konfiguration
 *   kommt aus .env.
 */
class EntferneUngenutzteDbObjekte extends Migration
{
    public function up()
    {
        $this->db->query('DROP VIEW IF EXISTS v_belege_mit_buchung');
        $this->db->query('DROP VIEW IF EXISTS v_abrechnungen_overview');

        $this->db->query('DROP TRIGGER IF EXISTS tr_ah_abrechnung_summe_insert');
        $this->db->query('DROP TRIGGER IF EXISTS tr_hv_abrechnung_summe_insert');
        $this->db->query('DROP TRIGGER IF EXISTS tr_abrechnung_summe_delete');

        $this->db->query('DROP TABLE IF EXISTS system_einstellungen');
    }

    public function down()
    {
        // Bewusst nicht umkehrbar: Die Objekte waren ungenutzt bzw. redundant.
        // Bei Bedarf lassen sie sich aus 2024-01-01-000002_SeedKassensystemData
        // wiederherstellen.
    }
}
