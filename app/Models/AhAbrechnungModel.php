<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * AhAbrechnungModel - AH² Abrechnungen
 *
 * Wie ein intelligenter Sammelordner:
 * - Verwaltet monatliche AH²-Abrechnungen
 * - Sammelt Belege für Abrechnung
 * - Erstellt Excel-Exports
 */
class AhAbrechnungModel extends Model
{
    protected $table = 'ah_abrechnungen';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'abrechnungsmonat', 'titel', 'erstellt_am', 'eingereicht_am',
        'bezahlt_am', 'status', 'gesamtsumme', 'notizen'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'abrechnungsmonat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
        'titel' => 'required|min_length[3]|max_length[255]',
        'erstellt_am' => 'required|valid_date',
        'status' => 'in_list[entwurf,ausstehend,eingereicht,bezahlt]'
    ];

    protected $validationMessages = [
        'abrechnungsmonat' => [
            'required' => 'Der Abrechnungsmonat ist erforderlich.',
            'regex_match' => 'Format muss YYYY-MM sein (z.B. 2024-06).'
        ],
        'titel' => [
            'required' => 'Ein Titel ist erforderlich.',
            'min_length' => 'Der Titel muss mindestens 3 Zeichen lang sein.'
        ]
    ];

    /**
     * Erstellt eine neue AH²-Abrechnung
     *
     * @param array $data
     * @return bool|int
     */
    public function erstelleAbrechnung($data)
    {
        // Prüfe ob für diesen Monat bereits eine Abrechnung existiert
        if ($this->abrechnungsmonatExistiert($data['abrechnungsmonat'])) {
            $this->errors = ['abrechnungsmonat' => 'Für diesen Monat existiert bereits eine AH²-Abrechnung.'];
            return false;
        }

        // Standard-Werte setzen
        $data['erstellt_am'] = $data['erstellt_am'] ?? date('Y-m-d');
        $data['status'] = 'entwurf';
        $data['gesamtsumme'] = 0.00;

        if (empty($data['titel'])) {
            $monatName = $this->getMonatName($data['abrechnungsmonat']);
            $data['titel'] = "AH² Abrechnung {$monatName}";
        }

        return $this->insert($data);
    }

    /**
     * Prüft ob eine Abrechnung für den Monat bereits existiert
     *
     * @param string $abrechnungsmonat
     * @param int|null $excludeId
     * @return bool
     */
    public function abrechnungsmonatExistiert($abrechnungsmonat, $excludeId = null)
    {
        $builder = $this->where('abrechnungsmonat', $abrechnungsmonat);

        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Holt alle Abrechnungen mit Beleg-Anzahl
     *
     * @return array
     */
    public function getAbrechnungenMitBeleganzahl()
    {
        return $this->select('
                ah_abrechnungen.*,
                COUNT(abrechnung_belege.id) as anzahl_belege
            ')
            ->join('abrechnung_belege', 'abrechnung_belege.abrechnung_id = ah_abrechnungen.id AND abrechnung_belege.abrechnung_typ = "ah"', 'left')
            ->groupBy('ah_abrechnungen.id')
            ->orderBy('ah_abrechnungen.abrechnungsmonat', 'DESC')
            ->findAll();
    }

    /**
     * Fügt einen Beleg zur Abrechnung hinzu
     *
     * @param int $abrechnungsId
     * @param int $belegId
     * @return bool
     */
    public function fuegeBeleghinzu($abrechnungsId, $belegId)
    {
        $db = \Config\Database::connect();

        // Prüfe ob Beleg bereits in einer anderen AH²-Abrechnung ist
        $builder = $db->table('abrechnung_belege');
        $existiert = $builder->where('beleg_id', $belegId)
                            ->where('abrechnung_typ', 'ah')
                            ->countAllResults() > 0;

        if ($existiert) {
            return false;
        }

    // Prüfe ob Beleg AH²-berechtigt ist
    $belegModel = new BelegModel();
    $beleg = $belegModel->find($belegId);

    if (!$beleg || $beleg['kategorie'] !== 'ah_berechtigt') {
        return false;
    }

    // Füge zur Abrechnung hinzu
    $data = [
        'beleg_id' => $belegId,
        'abrechnung_typ' => 'ah',
        'abrechnung_id' => $abrechnungsId
    ];

    $inserted = $builder->insert($data);

    if ($inserted) {
        // Beleg-Status aktualisieren
        $belegModel->update($belegId, ['status' => 'in_abrechnung']);

        // Gesamtsumme neu berechnen (wird durch Trigger gemacht, aber zur Sicherheit)
        $this->berechneGesamtsumme($abrechnungsId);
    }

    return $inserted;
    }

    /**
     * Entfernt einen Beleg aus der Abrechnung
     *
     * @param int $abrechnungsId
     * @param int $belegId
     * @return bool
     */
    public function entferneBeleg($abrechnungsId, $belegId)
    {
        $db = \Config\Database::connect();

        // Entferne aus Abrechnung
        $builder = $db->table('abrechnung_belege');
        $deleted = $builder->where('beleg_id', $belegId)
            ->where('abrechnung_typ', 'ah')
            ->where('abrechnung_id', $abrechnungsId)
            ->delete();

        if ($deleted) {
            // Beleg-Status zurücksetzen
            $belegModel = new BelegModel();
            $belegModel->update($belegId, ['status' => 'erfasst']);

            // Gesamtsumme neu berechnen
            $this->berechneGesamtsumme($abrechnungsId);
        }

        return $deleted;
    }

    /**
     * Holt alle Belege einer Abrechnung
     *
     * @param int $abrechnungsId
     * @return array
     */
    public function getBelege($abrechnungsId)
    {
        $belegModel = new BelegModel();
        return $belegModel->getBelegeFuerAbrechnung('ah', $abrechnungsId);
    }

    /**
     * Berechnet die Gesamtsumme einer Abrechnung neu
     *
     * @param int $abrechnungsId
     * @return bool
     * @throws \ReflectionException
     */
    public function berechneGesamtsumme($abrechnungsId)
    {
        $db = \Config\Database::connect();

        $query = $db->query("
                SELECT COALESCE(SUM(b.betrag), 0) as gesamt
                FROM abrechnung_belege ab
                JOIN belege b ON ab.beleg_id = b.id
                WHERE ab.abrechnung_id = ? AND ab.abrechnung_typ = 'ah'
            ", [$abrechnungsId]);

        $result = $query->getRow();
        $gesamtsumme = $result->gesamt;

        return $this->update($abrechnungsId, ['gesamtsumme' => $gesamtsumme]);
    }

    /**
     * Ändert den Status einer Abrechnung
     *
     * @param int $abrechnungsId
     * @param string $neuerStatus
     * @return bool
     */
    public function aendereStatus($abrechnungsId, $neuerStatus)
    {
        $erlaubteStatus = ['entwurf', 'ausstehend', 'eingereicht', 'bezahlt'];

        if (!in_array($neuerStatus, $erlaubteStatus)) {
            return false;
        }

        $updateData = ['status' => $neuerStatus];

        // Datum-Felder setzen je nach Status
        switch ($neuerStatus) {
            case 'eingereicht':
                $updateData['eingereicht_am'] = date('Y-m-d');
                break;
            case 'bezahlt':
                $updateData['bezahlt_am'] = date('Y-m-d');
                // Belege als abgerechnet markieren
                $this->markiereBelegeAlsAbgerechnet($abrechnungsId);
                break;
        }

        return $this->update($abrechnungsId, $updateData);
    }

    /**
     * Markiert alle Belege einer Abrechnung als abgerechnet
     *
     * @param int $abrechnungsId
     * @return bool
     */
    private function markiereBelegeAlsAbgerechnet($abrechnungsId)
    {
        $belege = $this->getBelege($abrechnungsId);
        $belegIds = array_column($belege, 'id');

        if (!empty($belegIds)) {
            $belegModel = new BelegModel();
            return $belegModel->updateStatus($belegIds, 'abgerechnet');
        }

        return true;
    }

    /**
     * Holt verfügbare Belege für AH²-Abrechnung
     *
     * @return array
     */
    public function getVerfuegbareBelege()
    {
        $belegModel = new BelegModel();
        return $belegModel->getBelegeVerfuegbar('ah_berechtigt');
    }

    /**
     * Erstellt Excel-Export-Daten
     *
     * @param int $abrechnungsId
     * @return array
     */
    public function getExportDaten($abrechnungsId)
    {
        $abrechnung = $this->find($abrechnungsId);
        $belege = $this->getBelege($abrechnungsId);

        if (!$abrechnung) {
            return null;
        }

        $exportDaten = [
            'abrechnung' => $abrechnung,
            'belege' => [],
            'gesamtsumme' => 0
        ];

        foreach ($belege as $beleg) {
            $exportDaten['belege'][] = [
                'Belegnummer' => $beleg['belegnummer'],
                'Datum' => date('d.m.Y', strtotime($beleg['rechnungsdatum'])),
                'Beschreibung' => $beleg['beschreibung'],
                'Lieferant' => $beleg['lieferant'] ?? '',
                'Betrag' => $beleg['betrag'],
                'Dateipfad' => $beleg['dateipfad']
            ];
            $exportDaten['gesamtsumme'] += $beleg['betrag'];
        }

        return $exportDaten;
    }

    /**
     * Holt Dashboard-Statistiken
     *
     * @return array
     */
    public function getDashboardStats()
    {
        return [
            'gesamt_abrechnungen' => $this->countAll(),
            'entwuerfe' => $this->where('status', 'entwurf')->countAllResults(),
            'ausstehend' => $this->where('status', 'ausstehend')->countAllResults(),
            'eingereicht' => $this->where('status', 'eingereicht')->countAllResults(),
            'aktueller_monat' => $this->where('abrechnungsmonat', date('Y-m'))->first(),
            'letzte_abrechnung' => $this->orderBy('created_at', 'DESC')->first()
        ];
    }

    /**
     * Konvertiert Monat in deutschen Namen
     *
     * @param string $abrechnungsmonat Format: Y-m
     * @return string
     */
    private function getMonatName($abrechnungsmonat)
    {
        $monate = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März',
            '04' => 'April', '05' => 'Mai', '06' => 'Juni',
            '07' => 'Juli', '08' => 'August', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'
        ];

        [$jahr, $monat] = explode('-', $abrechnungsmonat);
        return $monate[$monat] . ' ' . $jahr;
    }

    /**
     * Formatiert Status für Anzeige
     *
     * @param string $status
     * @return string
     */
    public function formatiereStatus($status)
    {
        $mapping = [
            'entwurf' => 'Entwurf',
            'ausstehend' => 'Ausstehend',
            'eingereicht' => 'Eingereicht',
            'bezahlt' => 'Bezahlt'
        ];

        return $mapping[$status] ?? $status;
    }

    /**
     * Holt Status-Badge-Klasse für Bootstrap
     *
     * @param string $status
     * @return string
     */
    public function getStatusBadgeClass($status)
    {
        $mapping = [
            'entwurf' => 'badge-secondary',
            'ausstehend' => 'badge-warning',
            'eingereicht' => 'badge-info',
            'bezahlt' => 'badge-success'
        ];

        return $mapping[$status] ?? 'badge-secondary';
    }

    /**
     * Formatiert Betrag für Anzeige
     *
     * @param float $betrag
     * @return string
     */
    public function formatiereBetrag($betrag)
    {
        return number_format($betrag, 2, ',', '.') . ' €';
    }
}