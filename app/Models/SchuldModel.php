<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SchuldModel - Schuldenliste (Issue #27)
 *
 * Ledger für Forderungen und Verbindlichkeiten pro Person:
 * - Person ist Freitext (keine Personen-Tabelle), Gruppierung über den Namen
 * - Rückzahlungen werden als negativer Betrag erfasst → offener Stand = SUM
 * - Forderungen und Verbindlichkeiten werden getrennt summiert (Inventur)
 */
class SchuldModel extends Model
{
    protected $table = 'schulden';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'person', 'typ', 'kategorie', 'datum', 'grund', 'betrag'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'person' => 'required|min_length[2]|max_length[100]',
        'typ' => 'required|in_list[forderung,verbindlichkeit]',
        'kategorie' => 'required|in_list[getraenke,abrechnung,sonstige]',
        'datum' => 'required|valid_date',
        'grund' => 'required|min_length[3]|max_length[255]',
        // Kein greater_than[0]: Rückzahlungen sind negative Beträge.
        // Betrag == 0 wird im Controller abgelehnt.
        'betrag' => 'required|decimal',
    ];

    protected $validationMessages = [
        'person' => [
            'required' => 'Bitte geben Sie eine Person an.',
            'min_length' => 'Der Name muss mindestens 2 Zeichen lang sein.',
        ],
        'typ' => [
            'required' => 'Bitte wählen Sie einen Typ aus.',
            'in_list' => 'Bitte wählen Sie einen gültigen Typ aus.',
        ],
        'kategorie' => [
            'required' => 'Bitte wählen Sie eine Kategorie aus.',
            'in_list' => 'Bitte wählen Sie eine gültige Kategorie aus.',
        ],
        'datum' => [
            'required' => 'Das Datum ist erforderlich.',
            'valid_date' => 'Bitte geben Sie ein gültiges Datum ein.',
        ],
        'grund' => [
            'required' => 'Ein Grund ist erforderlich.',
            'min_length' => 'Der Grund muss mindestens 3 Zeichen lang sein.',
        ],
        'betrag' => [
            'required' => 'Der Betrag ist erforderlich.',
            'decimal' => 'Bitte geben Sie einen gültigen Betrag ein.',
        ],
    ];

    /**
     * Übersicht: eine Zeile pro Person mit offenen Summen
     *
     * @return array
     */
    public function getPersonenUebersicht()
    {
        return $this->select("
                person,
                SUM(CASE WHEN typ = 'forderung' THEN betrag ELSE 0 END) AS forderungen_gesamt,
                SUM(CASE WHEN typ = 'forderung' AND kategorie = 'getraenke' THEN betrag ELSE 0 END) AS forderungen_getraenke,
                SUM(CASE WHEN typ = 'verbindlichkeit' THEN betrag ELSE 0 END) AS verbindlichkeiten_gesamt,
                COUNT(*) AS anzahl,
                MAX(datum) AS letzter_eintrag
            ")
            ->groupBy('person')
            ->orderBy('person')
            ->findAll();
    }

    /**
     * Alle Einträge einer Person (neueste zuerst) — die Nachverfolgungs-Ansicht
     *
     * @return array
     */
    public function getEintraegeFuerPerson(string $person)
    {
        return $this->where('person', $person)
            ->orderBy('datum', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Bereits verwendete Personennamen für die Datalist im Formular
     *
     * @return array<string>
     */
    public function getPersonenNamen()
    {
        $zeilen = $this->distinct()
            ->select('person')
            ->orderBy('person')
            ->findAll();

        return array_column($zeilen, 'person');
    }

    /**
     * Summen je Typ und Kategorie für die Inventur
     *
     * Grundgerüst mit Nullwerten (wie berechneKontostaende), damit auch bei
     * leerer Tabelle eine vollständige Struktur zurückkommt.
     *
     * @return array{forderung: array, verbindlichkeit: array}
     */
    public function berechneInventur()
    {
        $kategorien = ['getraenke', 'abrechnung', 'sonstige'];

        $inventur = [];
        foreach (['forderung', 'verbindlichkeit'] as $typ) {
            $inventur[$typ] = array_fill_keys($kategorien, 0.0);
            $inventur[$typ]['summe'] = 0.0;
        }

        $zeilen = $this->select('typ, kategorie, SUM(betrag) AS summe')
            ->groupBy(['typ', 'kategorie'])
            ->get()
            ->getResultArray();

        foreach ($zeilen as $zeile) {
            if (!isset($inventur[$zeile['typ']][$zeile['kategorie']])) {
                continue;
            }
            $inventur[$zeile['typ']][$zeile['kategorie']] = (float) $zeile['summe'];
        }

        foreach ($inventur as $typ => $werte) {
            $inventur[$typ]['summe'] = array_sum(array_intersect_key($werte, array_flip($kategorien)));
        }

        return $inventur;
    }

    /**
     * Summe aller offenen Forderungen (für die Dashboard-Karte)
     *
     * @return float
     */
    public function summeOffeneForderungen()
    {
        $zeile = $this->selectSum('betrag', 'summe')
            ->where('typ', 'forderung')
            ->get()
            ->getRowArray();

        return (float) ($zeile['summe'] ?? 0);
    }
}
