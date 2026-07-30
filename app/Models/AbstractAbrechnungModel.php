<?php

namespace App\Models;

use App\Libraries\GetraenkeRechnungImport;
use CodeIgniter\Model;

/**
 * AbstractAbrechnungModel - gemeinsame Basis für AH²- und HV-Abrechnungen
 *
 * Wie ein intelligenter Sammelordner:
 * - Verwaltet monatliche Abrechnungen
 * - Sammelt Belege für Abrechnung
 * - Erstellt Excel-Exports
 *
 * Die Subklassen (AhAbrechnungModel/HvAbrechnungModel) setzen nur noch
 * Tabelle, Typ-Diskriminator, Label und Beleg-Kategorie — die gesamte Logik
 * (inkl. der Beleg-Status-Invarianten) lebt hier genau EINMAL. Pendant zur
 * Controller-Abstraktion AbstractAbrechnungenController.
 */
abstract class AbstractAbrechnungModel extends Model
{
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
     * Typ-Diskriminator in abrechnung_belege: 'ah' | 'hv'
     */
    protected string $typ;

    /**
     * Kurzlabel für Default-Titel und Fehlermeldungen ('AH²' | 'HV').
     * Bewusst NICHT identisch mit AbstractAbrechnungenController::$typName
     * ('AH²' | 'Heimverein'), das den ausgeschriebenen Namen für PDF/Mail meint.
     */
    protected string $typLabel;

    /**
     * Beleg-Kategorie, die für diesen Abrechnungstyp zugeordnet werden darf.
     */
    protected string $berechtigtKategorie;

    /**
     * Eigene Fachfehler (z.B. Doppelmonat-Guard), die keine Validierungsregel
     * abdeckt. Werden von errors() vor die CI4-Fehler gezogen — sonst kämen sie
     * beim Aufrufer nie an (BaseModel::errors() liest nur Validation + DB).
     */
    protected array $eigeneFehler = [];

    /**
     * Erstellt eine neue Abrechnung
     *
     * @param array $data
     * @return bool|int
     */
    public function erstelleAbrechnung($data)
    {
        $this->eigeneFehler = [];

        // Prüfe ob für diesen Monat bereits eine Abrechnung existiert
        if ($this->abrechnungsmonatExistiert($data['abrechnungsmonat'])) {
            $this->eigeneFehler = [
                'abrechnungsmonat' => "Für diesen Monat existiert bereits eine {$this->typLabel}-Abrechnung."
            ];
            return false;
        }

        // Standard-Werte setzen
        $data['erstellt_am'] = $data['erstellt_am'] ?? date('Y-m-d');
        $data['status'] = 'entwurf';
        $data['gesamtsumme'] = 0.00;

        if (empty($data['titel'])) {
            $monatName = $this->getMonatName($data['abrechnungsmonat']);
            $data['titel'] = "{$this->typLabel} Abrechnung {$monatName}";
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
        return $this->select("
                {$this->table}.*,
                COUNT(abrechnung_belege.id) as anzahl_belege
            ")
            ->join(
                'abrechnung_belege',
                "abrechnung_belege.abrechnung_id = {$this->table}.id AND abrechnung_belege.abrechnung_typ = \"{$this->typ}\"",
                'left'
            )
            ->groupBy("{$this->table}.id")
            ->orderBy("{$this->table}.abrechnungsmonat", 'DESC')
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
        return $belegModel->getBelegeFuerAbrechnung($this->typ, $abrechnungsId);
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
                WHERE ab.abrechnung_id = ? AND ab.abrechnung_typ = ?
            ", [$abrechnungsId, $this->typ]);

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
     * Holt verfügbare Belege für diesen Abrechnungstyp
     *
     * @return array
     */
    public function getVerfuegbareBelege()
    {
        $belegModel = new BelegModel();
        return $belegModel->getBelegeVerfuegbar($this->berechtigtKategorie);
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
     * Delegiert an die eine Monatsnamen-Quelle der App.
     *
     * @param string $abrechnungsmonat Format: Y-m
     * @return string
     */
    public function getMonatName($abrechnungsmonat)
    {
        return GetraenkeRechnungImport::monatsName($abrechnungsmonat);
    }

    /**
     * Fehler des letzten Schreibvorgangs — eigene Fachfehler zuerst.
     *
     * @param bool $forceDB
     * @return array|string|null
     */
    public function errors(bool $forceDB = false)
    {
        if (!$forceDB && $this->eigeneFehler !== []) {
            return $this->eigeneFehler;
        }

        return parent::errors($forceDB);
    }
}
