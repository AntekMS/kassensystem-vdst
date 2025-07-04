<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * BelegModel - Herzstück des Kassensystems
 *
 * Wie ein intelligenter Aktenverwalter:
 * - Generiert automatisch Belegnummern basierend auf Rechnungsdatum
 * - Verwaltet Datei-Uploads und Umbenennungen
 * - Stellt Belege für Abrechnungen bereit
 */
class BelegModel extends Model
{
    protected $table = 'belege';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'belegnummer', 'rechnungsdatum', 'eingabedatum', 'beschreibung',
        'betrag', 'lieferant', 'dateiname_original', 'dateiname_system',
        'dateipfad', 'dateityp', 'dateigroesse', 'kategorie', 'status', 'notizen'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'rechnungsdatum' => 'required|valid_date',
        'beschreibung' => 'required|min_length[3]|max_length[500]',
        'betrag' => 'required|decimal|greater_than[0]',
        'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]',
        'status' => 'in_list[erfasst,in_abrechnung,abgerechnet,bezahlt]'
    ];

    protected $validationMessages = [
        'rechnungsdatum' => [
            'required' => 'Das Rechnungsdatum ist erforderlich.',
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
        ]
    ];

    /**
     * Generiert die nächste Belegnummer für ein bestimmtes Rechnungsdatum
     *
     * Funktioniert wie ein Aktenzeichen-Generator:
     * - Format: YYYY-MM-DD-001
     * - Basiert auf Rechnungsdatum (nicht Eingabedatum!)
     * - Automatische Nummerierung pro Tag
     *
     * @param string $rechnungsdatum Format: Y-m-d
     * @return string Belegnummer (z.B. "2024-06-15-001")
     */
    public function generiereNaechsteBelegnummer($rechnungsdatum)
    {
        // Formatiere Datum zu YYYY-MM-DD
        $datum = date('Y-m-d', strtotime($rechnungsdatum));

        // Finde höchste Nummer für diesen Tag
        $query = $this->select('belegnummer')
            ->where('DATE(rechnungsdatum)', $datum)
            ->orderBy('belegnummer', 'DESC')
            ->limit(1)
            ->get();

        $letzteNummer = $query->getRow();

        if ($letzteNummer) {
            // Extrahiere die laufende Nummer (letzten 3 Stellen)
            $teile = explode('-', $letzteNummer->belegnummer);
            $laufendeNummer = (int)end($teile) + 1;
        } else {
            // Erster Beleg für diesen Tag
            $laufendeNummer = 1;
        }

        // Generiere neue Belegnummer mit führenden Nullen
        return $datum . '-' . str_pad($laufendeNummer, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Prüft ob eine Belegnummer bereits existiert
     *
     * @param string $belegnummer
     * @param int|null $excludeId ID ausschließen (für Updates)
     * @return bool
     */
    public function belegnummerExistiert($belegnummer, $excludeId = null)
    {
        $builder = $this->where('belegnummer', $belegnummer);

        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Holt alle Belege für eine bestimmte Kategorie und Status
     *
     * Wie ein Filter im Aktenordner:
     * - Nur AH²-berechtigte Belege
     * - Nur verfügbare (nicht bereits abgerechnet)
     *
     * @param string $kategorie
     * @param array $status
     * @return array
     */
    public function getBelegeVerfuegbar($kategorie = null, $status = ['erfasst', 'in_abrechnung'])
    {
        $builder = $this->select('id, belegnummer, rechnungsdatum, beschreibung, betrag, lieferant, status')
            ->whereIn('status', $status)
            ->orderBy('rechnungsdatum', 'ASC');

        if ($kategorie) {
            $builder->where('kategorie', $kategorie);
        }

        return $builder->findAll();
    }

    /**
     * Holt Belege für eine bestimmte Abrechnung
     *
     * @param string $abrechnungsTyp 'ah' oder 'hv'
     * @param int $abrechnungsId
     * @return array
     */
    public function getBelegeFuerAbrechnung($abrechnungsTyp, $abrechnungsId)
    {
        return $this->select('belege.*, abrechnung_belege.hinzugefuegt_am')
            ->join('abrechnung_belege', 'abrechnung_belege.beleg_id = belege.id')
            ->where('abrechnung_belege.abrechnung_typ', $abrechnungsTyp)
            ->where('abrechnung_belege.abrechnung_id', $abrechnungsId)
            ->orderBy('belege.rechnungsdatum', 'ASC')
            ->findAll();
    }

    /**
     * Aktualisiert den Status von Belegen
     *
     * @param array $belegIds
     * @param string $neuerStatus
     * @return bool
     */
    public function updateStatus(array $belegIds, $neuerStatus)
    {
        if (empty($belegIds)) {
            return false;
        }

        return $this->whereIn('id', $belegIds)
            ->set(['status' => $neuerStatus])
            ->update();
    }

    /**
     * Sucht Belege nach verschiedenen Kriterien
     *
     * @param array $filter
     * @return array
     */
    public function sucheBelege($filter = [])
    {
        $builder = $this->select('*');

        // Suchtext in Beschreibung oder Lieferant
        if (!empty($filter['suche'])) {
            $builder->groupStart()
                ->like('beschreibung', $filter['suche'])
                ->orLike('lieferant', $filter['suche'])
                ->orLike('belegnummer', $filter['suche'])
                ->groupEnd();
        }

        // Datum-Filter
        if (!empty($filter['datum_von'])) {
            $builder->where('rechnungsdatum >=', $filter['datum_von']);
        }

        if (!empty($filter['datum_bis'])) {
            $builder->where('rechnungsdatum <=', $filter['datum_bis']);
        }

        // Kategorie-Filter
        if (!empty($filter['kategorie'])) {
            $builder->where('kategorie', $filter['kategorie']);
        }

        // Status-Filter
        if (!empty($filter['status'])) {
            $builder->where('status', $filter['status']);
        }

        // Betrag-Filter
        if (!empty($filter['betrag_min'])) {
            $builder->where('betrag >=', $filter['betrag_min']);
        }

        if (!empty($filter['betrag_max'])) {
            $builder->where('betrag <=', $filter['betrag_max']);
        }

        return $builder->orderBy('rechnungsdatum', 'DESC')
            ->findAll();
    }

    /**
     * Generiert Dateiname für das System basierend auf Belegnummer
     *
     * @param string $belegnummer
     * @param string $originalExtension
     * @return string
     */
    public function generiereSystemDateiname($belegnummer, $originalExtension)
    {
        return $belegnummer . '.' . strtolower($originalExtension);
    }

    /**
     * Generiert Dateipfad basierend auf Rechnungsdatum
     *
     * Struktur: uploads/belege/YYYY/MM/
     *
     * @param string $rechnungsdatum
     * @return string
     */
    public function generiereDateipfad($rechnungsdatum)
    {
        $jahr = date('Y', strtotime($rechnungsdatum));
        $monat = date('m', strtotime($rechnungsdatum));

        return "uploads/belege/{$jahr}/{$monat}/";
    }

    /**
     * Holt Dashboard-Statistiken
     *
     * @return array
     */
    public function getDashboardStats()
    {
        return [
            'gesamt_belege' => $this->countAll(),
            'neue_belege' => $this->where('status', 'erfasst')->countAllResults(),
            'in_abrechnung' => $this->where('status', 'in_abrechnung')->countAllResults(),
            'abgerechnet' => $this->where('status', 'abgerechnet')->countAllResults(),
            'ah_berechtigt' => $this->where('kategorie', 'ah_berechtigt')
                ->where('status', 'erfasst')
                ->countAllResults(),
            'hv_berechtigt' => $this->where('kategorie', 'hv_berechtigt')
                ->where('status', 'erfasst')
                ->countAllResults(),
            'gesamtwert' => $this->selectSum('betrag')->get()->getRow()->betrag ?? 0
        ];
    }

    /**
     * Holt die neuesten Belege
     *
     * @param int $limit
     * @return array
     */
    public function getNeuesteBelege($limit = 10)
    {
        return $this->select('id, belegnummer, rechnungsdatum, beschreibung, betrag, status')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Validiert Dateityp
     *
     * @param string $dateityp
     * @return bool
     */
    public function istErlaubterDateityp($dateityp)
    {
        $erlaubteTypen = ['pdf', 'jpg', 'jpeg', 'png'];
        return in_array(strtolower($dateityp), $erlaubteTypen);
    }

    /**f
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