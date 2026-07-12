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
     * Neueste offene (entwurf/ausstehend) Abrechnung oder null.
     *
     * @return array|null
     */
    public function findeOffeneAbrechnung()
    {
        return $this->whereIn('status', ['entwurf', 'ausstehend'])
            ->orderBy('abrechnungsmonat', 'DESC')
            ->first();
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

        $aktuelle = $this->find($abrechnungsId);
        $alterStatus = $aktuelle['status'] ?? null;

        $updateData = ['status' => $neuerStatus];

        // Datum-Felder setzen je nach Status
        switch ($neuerStatus) {
            case 'eingereicht':
                $updateData['eingereicht_am'] = date('Y-m-d');
                break;
            case 'bezahlt':
                $updateData['bezahlt_am'] = date('Y-m-d');
                // Belege als abgerechnet markieren
                $this->setzeBelegeStatus($abrechnungsId, 'abgerechnet');
                break;
        }

        // Wird eine bezahlte Abrechnung zurückgestuft, zugeordnete Belege wieder freigeben
        if ($alterStatus === 'bezahlt' && $neuerStatus !== 'bezahlt') {
            $this->setzeBelegeStatus($abrechnungsId, 'in_abrechnung');
        }

        return $this->update($abrechnungsId, $updateData);
    }

    /**
     * Setzt den Status aller Belege einer Abrechnung.
     *
     * @param int $abrechnungsId
     * @param string $status Ziel-Status (z.B. 'abgerechnet', 'in_abrechnung')
     * @return bool
     */
    private function setzeBelegeStatus($abrechnungsId, $status)
    {
        $belege = $this->getBelege($abrechnungsId);
        $belegIds = array_column($belege, 'id');

        if (!empty($belegIds)) {
            $belegModel = new BelegModel();
            return $belegModel->updateStatus($belegIds, $status);
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

}