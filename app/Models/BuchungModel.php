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

        // *** ERWEITERTE SUCHE: Jetzt auch in Buchungs-Notizen ***
        if (!empty($filter['suche'])) {
            $builder->groupStart()
                ->like('buchungen.beschreibung', $filter['suche'])
                ->orLike('belege.lieferant', $filter['suche'])
                ->orLike('belege.belegnummer', $filter['suche'])
                ->orLike('buchungen.notizen', $filter['suche'])      // *** HIER: Buchungs-Notizen hinzugefügt ***
                ->orLike('belege.beschreibung', $filter['suche'])    // *** BONUS: Auch Beleg-Beschreibung ***
                ->orLike('belege.notizen', $filter['suche'])         // *** BONUS: Auch Beleg-Notizen ***
                ->groupEnd();
        }

        return $builder->orderBy('buchungen.buchungsdatum', 'DESC')
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

}