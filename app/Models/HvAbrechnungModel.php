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
     * Fügt einen Beleg zur HV-Abrechnung hinzu
     *
     * @param int $abrechnungsId
     * @param int $belegId
     * @return bool
     */
    public function fuegeBelegHinzu($abrechnungsId, $belegId)
    {
        $db = \Config\Database::connect();

        // Prüfe ob Beleg bereits in einer anderen HV-Abrechnung ist
        $builder = $db->table('abrechnung_belege');
        $existiert = $builder->where('beleg_id', $belegId)
                ->where('abrechnung_typ', 'hv')
                ->countAllResults() > 0;

        if ($existiert) {
            return false;
        }

        // Prüfe ob Beleg HV-berechtigt ist
        $belegModel = new BelegModel();
        $beleg = $belegModel->find($belegId);

        if (!$beleg || $beleg['kategorie'] !== 'hv_berechtigt') {
            return false;
        }

        // Füge zur Abrechnung hinzu
        $data = [
            'beleg_id' => $belegId,
            'abrechnung_typ' => 'hv',
            'abrechnung_id' => $abrechnungsId
        ];

        $inserted = $builder->insert($data);

        if ($inserted) {
            // Beleg-Status aktualisieren
            $belegModel->update($belegId, ['status' => 'in_abrechnung']);

            // Gesamtsumme neu berechnen
            $this->berechneGesamtsumme($abrechnungsId);
        }

        return $inserted;
    }

    /**
     * Entfernt einen Beleg aus der HV-Abrechnung
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
            ->where('abrechnung_typ', 'hv')
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
     * Markiert alle Belege einer HV-Abrechnung als abgerechnet
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
     * Erstellt Excel-Export-Daten für HV
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
                'Dateipfad' => $beleg['dateipfad'],
                'Begründung' => $this->generiereHausBegründung($beleg)
            ];
            $exportDaten['gesamtsumme'] += $beleg['betrag'];
        }

        return $exportDaten;
    }

    /**
     * Generiert eine Haus-spezifische Begründung für einen Beleg
     *
     * @param array $beleg
     * @return string
     */
    private function generiereHausBegründung($beleg)
    {
        $beschreibung = strtolower($beleg['beschreibung']);

        // Schlüsselwörter für automatische Begründungen
        $begruendungen = [
            'farbe' => 'Renovierung und Instandhaltung der Hausräume',
            'pinsel' => 'Renovierung und Instandhaltung der Hausräume',
            'streichen' => 'Renovierung und Instandhaltung der Hausräume',
            'regal' => 'Möblierung und Ausstattung der Gemeinschaftsräume',
            'schrank' => 'Möblierung und Ausstattung der Gemeinschaftsräume',
            'lampe' => 'Beleuchtung und elektrische Ausstattung',
            'glühbirne' => 'Beleuchtung und elektrische Ausstattung',
            'reinigung' => 'Reinigung und Hygiene der Hausräume',
            'putz' => 'Reinigung und Hygiene der Hausräume',
            'werkzeug' => 'Wartung und Reparatur der Hausausstattung',
            'schrauben' => 'Wartung und Reparatur der Hausausstattung',
            'küche' => 'Küchenausstattung und -wartung',
            'geschirr' => 'Küchenausstattung und -wartung'
        ];

        foreach ($begruendungen as $schluesselwort => $begruendung) {
            if (strpos($beschreibung, $schluesselwort) !== false) {
                return $begruendung;
            }
        }

        // Standard-Begründung falls kein Schlüsselwort gefunden
        return 'Notwendige Ausgabe für das Vereinshaus';
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

    /**
     * Holt HV-spezifische Begründungs-Vorlagen
     *
     * @return array
     */
    public function getBegruendungsVorlagen()
    {
        return [
            'Renovierung und Instandhaltung der Hausräume',
            'Möblierung und Ausstattung der Gemeinschaftsräume',
            'Beleuchtung und elektrische Ausstattung',
            'Reinigung und Hygiene der Hausräume',
            'Wartung und Reparatur der Hausausstattung',
            'Küchenausstattung und -wartung',
            'Sicherheitsmaßnahmen für das Vereinshaus',
            'Garten- und Außenbereichspflege',
            'Heizung und Warmwasserversorgung',
            'Notwendige Ausgabe für das Vereinshaus'
        ];
    }

    /**
     * Sucht HV-Abrechnungen nach Kriterien
     *
     * @param array $filter
     * @return array
     */
    public function sucheAbrechnungen($filter = [])
    {
        $builder = $this->select('*');

        if (!empty($filter['suche'])) {
            $builder->groupStart()
                ->like('titel', $filter['suche'])
                ->orLike('begruendung', $filter['suche'])
                ->orLike('abrechnungsmonat', $filter['suche'])
                ->groupEnd();
        }

        if (!empty($filter['status'])) {
            $builder->where('status', $filter['status']);
        }

        if (!empty($filter['jahr'])) {
            $builder->like('abrechnungsmonat', $filter['jahr'], 'after');
        }

        return $builder->orderBy('abrechnungsmonat', 'DESC')->findAll();
    }
}