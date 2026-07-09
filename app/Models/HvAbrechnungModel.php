<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * HvAbrechnungModel - Heimverein Abrechnungen
 *
 * Wie ein intelligenter Sammelordner für Haus-Ausgaben:
 * - Verwaltet monatliche HV-Abrechnungen
 * - Sammelt haus-spezifische Belege
 * - Erstellt Excel-Exports mit Begründungen
 */
class HvAbrechnungModel extends Model
{
    protected $table = 'hv_abrechnungen';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'abrechnungsmonat', 'titel', 'erstellt_am', 'eingereicht_am',
        'bezahlt_am', 'status', 'gesamtsumme', 'begruendung', 'notizen'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'abrechnungsmonat' => 'required|regex_match[/^\d{4}-\d{2}$/]',
        'titel' => 'required|min_length[3]|max_length[255]',
        'erstellt_am' => 'required|valid_date',
        'status' => 'in_list[entwurf,ausstehend,eingereicht,bezahlt]',
        'begruendung' => 'max_length[1000]'
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
     * Erstellt eine neue HV-Abrechnung
     *
     * @param array $data
     * @return bool|int
     */
    public function erstelleAbrechnung($data)
    {
        // Prüfe ob für diesen Monat bereits eine Abrechnung existiert
        if ($this->abrechnungsmonatExistiert($data['abrechnungsmonat'])) {
            $this->errors = ['abrechnungsmonat' => 'Für diesen Monat existiert bereits eine HV-Abrechnung.'];
            return false;
        }

        // Standard-Werte setzen
        $data['erstellt_am'] = $data['erstellt_am'] ?? date('Y-m-d');
        $data['status'] = 'entwurf';
        $data['gesamtsumme'] = 0.00;

        if (empty($data['titel'])) {
            $monatName = $this->getMonatName($data['abrechnungsmonat']);
            $data['titel'] = "HV Abrechnung {$monatName}";
        }

        return $this->insert($data);
    }

    /**
     * Prüft ob eine HV-Abrechnung für den Monat bereits existiert
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
     * Holt alle HV-Abrechnungen mit Beleg-Anzahl
     *
     * @return array
     */
    public function getAbrechnungenMitBeleganzahl()
    {
        return $this->select('
                hv_abrechnungen.*,
                COUNT(abrechnung_belege.id) as anzahl_belege
            ')
            ->join('abrechnung_belege', 'abrechnung_belege.abrechnung_id = hv_abrechnungen.id AND abrechnung_belege.abrechnung_typ = "hv"', 'left')
            ->groupBy('hv_abrechnungen.id')
            ->orderBy('hv_abrechnungen.abrechnungsmonat', 'DESC')
            ->findAll();
    }

    /**
     * Holt alle Belege einer HV-Abrechnung
     *
     * @param int $abrechnungsId
     * @return array
     */
    public function getBelege($abrechnungsId)
    {
        $belegModel = new BelegModel();
        return $belegModel->getBelegeFuerAbrechnung('hv', $abrechnungsId);
    }

    /**
     * Berechnet die Gesamtsumme einer HV-Abrechnung neu
     *
     * @param int $abrechnungsId
     * @return bool
     */
    public function berechneGesamtsumme($abrechnungsId)
    {
        $db = \Config\Database::connect();

        $query = $db->query("
            SELECT COALESCE(SUM(b.betrag), 0) as gesamt
            FROM abrechnung_belege ab
            JOIN belege b ON ab.beleg_id = b.id
            WHERE ab.abrechnung_id = ? AND ab.abrechnung_typ = 'hv'
        ", [$abrechnungsId]);

        $result = $query->getRow();
        $gesamtsumme = $result->gesamt;

        return $this->update($abrechnungsId, ['gesamtsumme' => $gesamtsumme]);
    }

    /**
     * Ändert den Status einer HV-Abrechnung
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
     * Setzt den Status aller Belege einer HV-Abrechnung.
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
     * Holt verfügbare Belege für HV-Abrechnung
     *
     * @return array
     */
    public function getVerfuegbareBelege()
    {
        $belegModel = new BelegModel();
        return $belegModel->getBelegeVerfuegbar('hv_berechtigt');
    }

    /**
     * Holt Dashboard-Statistiken für HV
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