<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * VDStE Kassensystem - Basis-Daten Migration
 *
 * Fügt System-Einstellungen und Test-Daten ein
 * Ausführen nach der ersten Migration
 */
class SeedKassensystemData extends Migration
{
    public function up()
    {
        // =====================================================
        // System-Einstellungen einfügen
        // =====================================================
        $systemEinstellungen = [
            [
                'schluessel' => 'max_upload_size',
                'wert' => '10485760',
                'beschreibung' => 'Maximale Upload-Größe in Bytes (10MB)'
            ],
            [
                'schluessel' => 'allowed_file_types',
                'wert' => 'pdf,jpg,jpeg,png',
                'beschreibung' => 'Erlaubte Dateitypen für Upload'
            ],
            [
                'schluessel' => 'upload_path',
                'wert' => 'uploads/belege/',
                'beschreibung' => 'Basis-Pfad für Beleg-Uploads'
            ],
            [
                'schluessel' => 'kassenwart_name',
                'wert' => 'Kassenwart VDStE',
                'beschreibung' => 'Name für Excel-Exporte'
            ],
            [
                'schluessel' => 'verein_name',
                'wert' => 'Verein deutscher Studenten zu Erlangen',
                'beschreibung' => 'Vollständiger Vereinsname'
            ],
            [
                'schluessel' => 'app_version',
                'wert' => '1.0.0',
                'beschreibung' => 'Aktuelle Version des Kassensystems'
            ],
            [
                'schluessel' => 'backup_interval',
                'wert' => '7',
                'beschreibung' => 'Backup-Intervall in Tagen'
            ]
        ];

        $this->db->table('system_einstellungen')->insertBatch($systemEinstellungen);

        // =====================================================
        // Test-Daten für Entwicklung (optional)
        // =====================================================

        // Beispiel-Beleg 1: AH²-berechtigt
        $this->db->table('belege')->insert([
            'belegnummer' => '2024-06-15-001',
            'rechnungsdatum' => '2024-06-15',
            'eingabedatum' => date('Y-m-d'),
            'beschreibung' => 'Getränke für Sommerfest',
            'betrag' => 123.45,
            'lieferant' => 'Getränke Müller GmbH',
            'dateiname_original' => 'rechnung_getraenke.pdf',
            'dateiname_system' => '2024-06-15-001.pdf',
            'dateipfad' => 'uploads/belege/2024/06/2024-06-15-001.pdf',
            'dateityp' => 'pdf',
            'dateigroesse' => 245760,
            'kategorie' => 'ah_berechtigt',
            'status' => 'erfasst'
        ]);

        // Beispiel-Beleg 2: HV-berechtigt
        $this->db->table('belege')->insert([
            'belegnummer' => '2024-06-16-001',
            'rechnungsdatum' => '2024-06-16',
            'eingabedatum' => date('Y-m-d'),
            'beschreibung' => 'Farbe für Wände im Gemeinschaftsraum',
            'betrag' => 89.50,
            'lieferant' => 'Baumarkt Erlangen',
            'dateiname_original' => 'baumarkt_farbe.jpg',
            'dateiname_system' => '2024-06-16-001.jpg',
            'dateipfad' => 'uploads/belege/2024/06/2024-06-16-001.jpg',
            'dateityp' => 'jpg',
            'dateigroesse' => 1234567,
            'kategorie' => 'hv_berechtigt',
            'status' => 'erfasst'
        ]);

        // Beispiel-Beleg 3: Normal (ohne Erstattung)
        $this->db->table('belege')->insert([
            'belegnummer' => '2024-06-17-001',
            'rechnungsdatum' => '2024-06-17',
            'eingabedatum' => date('Y-m-d'),
            'beschreibung' => 'Büromaterial für Kassenwart',
            'betrag' => 25.99,
            'lieferant' => 'Büro-Center',
            'dateiname_original' => 'buero_material.pdf',
            'dateiname_system' => '2024-06-17-001.pdf',
            'dateipfad' => 'uploads/belege/2024/06/2024-06-17-001.pdf',
            'dateityp' => 'pdf',
            'dateigroesse' => 567890,
            'kategorie' => 'normal',
            'status' => 'erfasst'
        ]);

        // Beispiel-Buchungen
        $buchungen = [
            [
                'beleg_id' => 1, // Verknüpft mit erstem Beleg
                'buchungsdatum' => '2024-06-16',
                'beschreibung' => 'Getränke für Sommerfest - Zahlung per Überweisung',
                'betrag' => 123.45,
                'konto_typ' => 'aktivenkasse',
                'buchungsart' => 'ausgabe'
            ],
            [
                'beleg_id' => 2, // Verknüpft mit zweitem Beleg
                'buchungsdatum' => '2024-06-17',
                'beschreibung' => 'Farbe für Hausrenovierung - Bar bezahlt',
                'betrag' => 89.50,
                'konto_typ' => 'barkasse',
                'buchungsart' => 'ausgabe'
            ],
            [
                'beleg_id' => null, // Ohne Beleg
                'buchungsdatum' => '2024-06-18',
                'beschreibung' => 'Mitgliedsbeitrag Sommersemester',
                'betrag' => 50.00,
                'konto_typ' => 'aktivenkasse',
                'buchungsart' => 'einnahme'
            ]
        ];

        $this->db->table('buchungen')->insertBatch($buchungen);

        // Beispiel AH²-Abrechnung
        $this->db->table('ah_abrechnungen')->insert([
            'abrechnungsmonat' => '2024-06',
            'titel' => 'AH² Abrechnung Juni 2024',
            'erstellt_am' => date('Y-m-d'),
            'status' => 'entwurf',
            'gesamtsumme' => 0.00
        ]);

        // Beispiel HV-Abrechnung
        $this->db->table('hv_abrechnungen')->insert([
            'abrechnungsmonat' => '2024-06',
            'titel' => 'HV Abrechnung Juni 2024 - Hausrenovierung',
            'erstellt_am' => date('Y-m-d'),
            'status' => 'entwurf',
            'gesamtsumme' => 0.00,
            'begruendung' => 'Renovierung der Gemeinschaftsräume'
        ]);

        // =====================================================
        // Views erstellen (für bessere Performance bei Reports)
        // =====================================================

        // View: Belege mit Buchungs-Info
        $this->db->query("
            CREATE VIEW v_belege_mit_buchung AS
            SELECT 
                b.id,
                b.belegnummer,
                b.rechnungsdatum,
                b.eingabedatum,
                b.beschreibung,
                b.betrag,
                b.lieferant,
                b.kategorie,
                b.status,
                b.dateipfad,
                bu.id as buchung_id,
                bu.buchungsdatum,
                bu.konto_typ
            FROM belege b
            LEFT JOIN buchungen bu ON b.id = bu.beleg_id
        ");

        // View: Abrechnungen-Übersicht
        $this->db->query("
            CREATE VIEW v_abrechnungen_overview AS
            SELECT 
                'ah' as typ,
                id,
                titel,
                abrechnungsmonat,
                status,
                gesamtsumme,
                erstellt_am,
                (SELECT COUNT(*) FROM abrechnung_belege ab WHERE ab.abrechnung_id = ah.id AND ab.abrechnung_typ = 'ah') as anzahl_belege
            FROM ah_abrechnungen ah
            UNION ALL
            SELECT 
                'hv' as typ,
                id,
                titel,
                abrechnungsmonat,
                status,
                gesamtsumme,
                erstellt_am,
                (SELECT COUNT(*) FROM abrechnung_belege ab WHERE ab.abrechnung_id = hv.id AND ab.abrechnung_typ = 'hv') as anzahl_belege
            FROM hv_abrechnungen hv
        ");

        // =====================================================
        // Trigger für automatische Summenberechnung
        // =====================================================

        // Trigger für AH² Abrechnungen bei INSERT
        $this->db->query("
            CREATE TRIGGER tr_ah_abrechnung_summe_insert
            AFTER INSERT ON abrechnung_belege
            FOR EACH ROW
            BEGIN
                IF NEW.abrechnung_typ = 'ah' THEN
                    UPDATE ah_abrechnungen 
                    SET gesamtsumme = (
                        SELECT COALESCE(SUM(b.betrag), 0)
                        FROM abrechnung_belege ab
                        JOIN belege b ON ab.beleg_id = b.id
                        WHERE ab.abrechnung_id = NEW.abrechnung_id 
                        AND ab.abrechnung_typ = 'ah'
                    )
                    WHERE id = NEW.abrechnung_id;
                END IF;
            END
        ");

        // Trigger für HV Abrechnungen bei INSERT
        $this->db->query("
            CREATE TRIGGER tr_hv_abrechnung_summe_insert
            AFTER INSERT ON abrechnung_belege
            FOR EACH ROW
            BEGIN
                IF NEW.abrechnung_typ = 'hv' THEN
                    UPDATE hv_abrechnungen 
                    SET gesamtsumme = (
                        SELECT COALESCE(SUM(b.betrag), 0)
                        FROM abrechnung_belege ab
                        JOIN belege b ON ab.beleg_id = b.id
                        WHERE ab.abrechnung_id = NEW.abrechnung_id 
                        AND ab.abrechnung_typ = 'hv'
                    )
                    WHERE id = NEW.abrechnung_id;
                END IF;
            END
        ");

        // Trigger für Löschungen
        $this->db->query("
            CREATE TRIGGER tr_abrechnung_summe_delete
            AFTER DELETE ON abrechnung_belege
            FOR EACH ROW
            BEGIN
                IF OLD.abrechnung_typ = 'ah' THEN
                    UPDATE ah_abrechnungen 
                    SET gesamtsumme = (
                        SELECT COALESCE(SUM(b.betrag), 0)
                        FROM abrechnung_belege ab
                        JOIN belege b ON ab.beleg_id = b.id
                        WHERE ab.abrechnung_id = OLD.abrechnung_id 
                        AND ab.abrechnung_typ = 'ah'
                    )
                    WHERE id = OLD.abrechnung_id;
                ELSEIF OLD.abrechnung_typ = 'hv' THEN
                    UPDATE hv_abrechnungen 
                    SET gesamtsumme = (
                        SELECT COALESCE(SUM(b.betrag), 0)
                        FROM abrechnung_belege ab
                        JOIN belege b ON ab.beleg_id = b.id
                        WHERE ab.abrechnung_id = OLD.abrechnung_id 
                        AND ab.abrechnung_typ = 'hv'
                    )
                    WHERE id = OLD.abrechnung_id;
                END IF;
            END
        ");
    }

    public function down()
    {
        // Trigger löschen
        $this->db->query("DROP TRIGGER IF EXISTS tr_ah_abrechnung_summe_insert");
        $this->db->query("DROP TRIGGER IF EXISTS tr_hv_abrechnung_summe_insert");
        $this->db->query("DROP TRIGGER IF EXISTS tr_abrechnung_summe_delete");

        // Views löschen
        $this->db->query("DROP VIEW IF EXISTS v_belege_mit_buchung");
        $this->db->query("DROP VIEW IF EXISTS v_abrechnungen_overview");

        // Alle Daten löschen
        $this->db->table('abrechnung_belege')->truncate();
        $this->db->table('buchungen')->truncate();
        $this->db->table('hv_abrechnungen')->truncate();
        $this->db->table('ah_abrechnungen')->truncate();
        $this->db->table('belege')->truncate();
        $this->db->table('system_einstellungen')->truncate();
    }
}