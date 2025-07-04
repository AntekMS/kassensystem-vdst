<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * BuchungModel - Kassenbuch-Verwaltung
 *
 * Wie ein digitales Kassenbuch:
 * - Verwaltet alle Ein- und Ausgaben
 * - Verknüpft optional mit Belegen
 * - Erstellt Excel-Exports für Kassenbuch
 */
class BuchungModel extends Model
{
    protected $table = 'buchungen';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'beleg_id', 'buchungsdatum', 'beschreibung', 'betrag',
        'konto_typ', 'buchungsart', 'notizen'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'buchungsdatum' => 'required|valid_date',
        'beschreibung' => 'required|min_length[3]|max_length[500]',
        'betrag' => 'required|decimal|greater_than[0]',
        'konto_typ' => 'required|in_list[aktivenkasse,getraenkekasse,barkasse]',
        'buchungsart' => 'required|in_list[ausgabe,einnahme]'
    ];

    protected $validationMessages = [
        'buchungsdatum' => [
            'required' => 'Das Buchungsdatum ist erforderlich.',
            'valid_date' => 'Bitte geben Sie ein gültiges Datum ein.'
        ],
        'beschreibung' => [
            'required' => 'Eine Beschreibung ist erforderlich.',
            'min_length' => 'Die Beschreibung muss mindestens 3 Zeichen lang sein.'
        ],
        'betrag' => [
            'required' => 'Der Betrag ist erforderlich.',
            'decimal' => 'Bitte geben Sie einen gültigen Betrag ein.',
            'greater_than' => 'Der Betrag muss größer als 0 sein.'
        ],
        'konto_typ' => [
            'required' => 'Bitte wählen Sie ein Konto aus.',
            'in_list' => 'Bitte wählen Sie ein gültiges Konto aus.'
        ]
    ];

    /**
     * Holt alle Buchungen mit Beleg-Informationen
     *
     * @param array $filter Optional: Filter-Kriterien
     * @return array
     */
    public function getBuchungenMitBelegen($filter = [])
    {
        $builder = $this->select('
                buchungen.*, 
                belege.belegnummer, 
                belege.rechnungsdatum,
                belege.lieferant,
                belege.dateipfad
            ')
            ->join('belege', 'belege.id = buchungen.beleg_id', 'left');

        // Filter anwenden
        if (!empty($filter['datum_von'])) {
            $builder->where('buchungen.buchungsdatum >=', $filter['datum_von']);
        }

        if (!empty($filter['datum_bis'])) {
            $builder->where('buchungen.buchungsdatum <=', $filter['datum_bis']);
        }

        if (!empty($filter['konto_typ'])) {
            $builder->where('buchungen.konto_typ', $filter['konto_typ']);
        }

        if (!empty($filter['buchungsart'])) {
            $builder->where('buchungen.buchungsart', $filter['buchungsart']);
        }

        if (!empty($filter['suche'])) {
            $builder->groupStart()
                ->like('buchungen.beschreibung', $filter['suche'])
                ->orLike('belege.lieferant', $filter['suche'])
                ->orLike('belege.belegnummer', $filter['suche'])
                ->groupEnd();
        }

        return $builder->orderBy('buchungen.buchungsdatum', 'DESC')
            ->findAll();
    }

    /**
     * Holt Buchungen für einen bestimmten Zeitraum
     *
     * @param string $monat Format: Y-m
     * @return array
     */
    public function getBuchungenFuerMonat($monat)
    {
        return $this->select('
                buchungen.*, 
                belege.belegnummer, 
                belege.lieferant
            ')
            ->join('belege', 'belege.id = buchungen.beleg_id', 'left')
            ->where('DATE_FORMAT(buchungen.buchungsdatum, "%Y-%m")', $monat)
            ->orderBy('buchungen.buchungsdatum', 'ASC')
            ->findAll();
    }

    /**
     * Berechnet Kontostände
     *
     * @return array
     */
    public function berechneKontostaende()
    {
        $konten = ['aktivenkasse', 'getraenkekasse', 'barkasse'];
        $staende = [];

        foreach ($konten as $konto) {
            $einnahmen = $this->where('konto_typ', $konto)
                ->where('buchungsart', 'einnahme')
                ->selectSum('betrag')
                ->get()
                ->getRow()
                ->betrag ?? 0;

            $ausgaben = $this->where('konto_typ', $konto)
                ->where('buchungsart', 'ausgabe')
                ->selectSum('betrag')
                ->get()
                ->getRow()
                ->betrag ?? 0;

            $staende[$konto] = [
                'einnahmen' => $einnahmen,
                'ausgaben' => $ausgaben,
                'saldo' => $einnahmen - $ausgaben
            ];
        }

        return $staende;
    }

    /**
     * Holt Buchungen ohne Beleg-Verknüpfung
     *
     * @return array
     */
    public function getBuchungenOhneBelege()
    {
        return $this->where('beleg_id IS NULL')
            ->orderBy('buchungsdatum', 'DESC')
            ->findAll();
    }

    /**
     * Erstellt eine neue Buchung mit optionaler Beleg-Verknüpfung
     *
     * @param array $data
     * @return bool|int
     */
    public function erstelleBuchung($data)
    {
        // Validierung
        if (!$this->validate($data)) {
            return false;
        }

        // Beleg-ID prüfen falls vorhanden
        if (!empty($data['beleg_id'])) {
            $belegModel = new BelegModel();
            $beleg = $belegModel->find($data['beleg_id']);

            if (!$beleg) {
                $this->errors = ['beleg_id' => 'Der ausgewählte Beleg existiert nicht.'];
                return false;
            }
        }

        return $this->insert($data);
    }

    /**
     * Holt Dashboard-Statistiken für Buchungen
     *
     * @return array
     */
    public function getDashboardStats()
    {
        $heute = date('Y-m-d');
        $dieserMonat = date('Y-m');

        return [
            'buchungen_heute' => $this->where('DATE(buchungsdatum)', $heute)->countAllResults(),
            'buchungen_monat' => $this->where('DATE_FORMAT(buchungsdatum, "%Y-%m")', $dieserMonat)->countAllResults(),
            'ohne_beleg' => $this->where('beleg_id IS NULL')->countAllResults(),
            'letzte_buchung' => $this->orderBy('created_at', 'DESC')->first()
        ];
    }

    /**
     * Holt die neuesten Buchungen
     *
     * @param int $limit
     * @return array
     */
    public function getNeuesteBuchungen($limit = 10)
    {
        return $this->select('
                buchungen.*, 
                belege.belegnummer
            ')
            ->join('belege', 'belege.id = buchungen.beleg_id', 'left')
            ->orderBy('buchungen.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Sucht verfügbare Belege für Buchungs-Verknüpfung
     *
     * @return array
     */
    public function getVerfuegbareBelegeFuerBuchung()
    {
        $belegModel = new BelegModel();

        // Belege die noch keine Buchung haben
        $belege = $belegModel->select('id, belegnummer, rechnungsdatum, beschreibung, betrag, lieferant')
            ->where('id NOT IN (SELECT COALESCE(beleg_id, 0) FROM buchungen WHERE beleg_id IS NOT NULL)')
            ->orderBy('rechnungsdatum', 'DESC')
            ->findAll();

        return $belege;
    }

    /**
     * Exportiert Kassenbuch-Daten für Excel
     *
     * @param array $filter
     * @return array
     */
    public function getKassenbuchExportDaten($filter = [])
    {
        $buchungen = $this->getBuchungenMitBelegen($filter);
        $exportDaten = [];

        foreach ($buchungen as $buchung) {
            $exportDaten[] = [
                'Datum' => date('d.m.Y', strtotime($buchung['buchungsdatum'])),
                'Belegnummer' => $buchung['belegnummer'] ?? 'ohne Beleg',
                'Beschreibung' => $buchung['beschreibung'],
                'Lieferant' => $buchung['lieferant'] ?? '',
                'Konto' => ucfirst($buchung['konto_typ']),
                'Einnahme' => $buchung['buchungsart'] === 'einnahme' ? $buchung['betrag'] : '',
                'Ausgabe' => $buchung['buchungsart'] === 'ausgabe' ? $buchung['betrag'] : '',
                'Notizen' => $buchung['notizen'] ?? ''
            ];
        }

        return $exportDaten;
    }

    /**
     * Berechnet Summen für Zeitraum
     *
     * @param string $datumVon
     * @param string $datumBis
     * @return array
     */
    public function berechneSummenFuerZeitraum($datumVon, $datumBis)
    {
        $builder = $this->where('buchungsdatum >=', $datumVon)
            ->where('buchungsdatum <=', $datumBis);

        $einnahmen = $builder->where('buchungsart', 'einnahme')
            ->selectSum('betrag')
            ->get()
            ->getRow()
            ->betrag ?? 0;

        $ausgaben = $this->where('buchungsdatum >=', $datumVon)
            ->where('buchungsdatum <=', $datumBis)
            ->where('buchungsart', 'ausgabe')
            ->selectSum('betrag')
            ->get()
            ->getRow()
            ->betrag ?? 0;

        return [
            'einnahmen' => $einnahmen,
            'ausgaben' => $ausgaben,
            'saldo' => $einnahmen - $ausgaben
        ];
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
     * Formatiert Konto-Typ für Anzeige
     *
     * @param string $kontoTyp
     * @return string
     */
    public function formatiereKontoTyp($kontoTyp)
    {
        $mapping = [
            'aktivenkasse' => 'Aktivenkasse',
            'getraenkekasse' => 'Getränkekasse',
            'barkasse' => 'Barkasse'
        ];

        return $mapping[$kontoTyp] ?? $kontoTyp;
    }

    /**
     * Formatiert Buchungsart für Anzeige
     *
     * @param string $buchungsart
     * @return string
     */
    public function formatiereBuchungsart($buchungsart)
    {
        return $buchungsart === 'einnahme' ? 'Einnahme' : 'Ausgabe';
    }
}