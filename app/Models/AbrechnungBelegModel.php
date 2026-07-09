<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * AbrechnungBelegModel - Verknüpfungs-Verwaltung
 *
 * Wie ein intelligenter Kreuzverweisindex:
 * - Verwaltet welche Belege in welcher Abrechnung sind
 * - Verhindert Doppelzuordnungen
 * - Ermöglicht flexible Beleg-Auswahl für Abrechnungen
 */
class AbrechnungBelegModel extends Model
{
    protected $table = 'abrechnung_belege';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'beleg_id', 'abrechnung_typ', 'abrechnung_id', 'hinzugefuegt_am'
    ];

    protected $useTimestamps = false; // Wir verwenden hinzugefuegt_am

    protected $validationRules = [
        'beleg_id' => 'required|integer',
        'abrechnung_typ' => 'required|in_list[ah,hv]',
        'abrechnung_id' => 'required|integer'
    ];

    protected $validationMessages = [
        'beleg_id' => [
            'required' => 'Eine Beleg-ID ist erforderlich.',
            'integer' => 'Die Beleg-ID muss eine Zahl sein.'
        ],
        'abrechnung_typ' => [
            'required' => 'Der Abrechnungstyp ist erforderlich.',
            'in_list' => 'Abrechnungstyp muss "ah" oder "hv" sein.'
        ],
        'abrechnung_id' => [
            'required' => 'Eine Abrechnungs-ID ist erforderlich.',
            'integer' => 'Die Abrechnungs-ID muss eine Zahl sein.'
        ]
    ];

    /**
     * Fügt einen Beleg zu einer Abrechnung hinzu
     */
    public function fuegeZuordnungHinzu($belegId, $abrechnungsTyp, $abrechnungsId)
    {
        // Prüfe ob Beleg bereits in dieser Abrechnung ist
        if ($this->istBelegInAbrechnung($belegId, $abrechnungsTyp, $abrechnungsId)) {
            $this->errors = ['duplicate' => 'Beleg ist bereits in dieser Abrechnung enthalten.'];
            return false;
        }

        // Prüfe ob Beleg bereits in einer anderen Abrechnung desselben Typs ist
        if ($this->istBelegInAnderenAbrechnungen($belegId, $abrechnungsTyp, $abrechnungsId)) {
            $this->errors = ['conflict' => 'Beleg ist bereits in einer anderen ' . strtoupper($abrechnungsTyp) . '-Abrechnung enthalten.'];
            return false;
        }

        // Validiere Beleg-Berechtigung
        if (!$this->istBelegBerechtigt($belegId, $abrechnungsTyp)) {
            $berechtigung = $abrechnungsTyp === 'ah' ? 'AH²' : 'HV';
            $this->errors = ['permission' => "Beleg ist nicht für {$berechtigung}-Abrechnungen berechtigt."];
            return false;
        }

        // Validiere Abrechnung existiert
        if (!$this->abrechnungExistiert($abrechnungsTyp, $abrechnungsId)) {
            $this->errors = ['abrechnung' => 'Die angegebene Abrechnung existiert nicht.'];
            return false;
        }

        // Füge Zuordnung hinzu
        $data = [
            'beleg_id' => $belegId,
            'abrechnung_typ' => $abrechnungsTyp,
            'abrechnung_id' => $abrechnungsId,
            'hinzugefuegt_am' => date('Y-m-d H:i:s')
        ];

        $result = $this->insert($data);

        if ($result) {
            // Beleg-Status aktualisieren
            $this->aktualisiereBeregStatus($belegId, 'in_abrechnung');
        }

        return $result;
    }

    /**
     * Entfernt einen Beleg aus einer Abrechnung
     */
    public function entferneZuordnung($belegId, $abrechnungsTyp, $abrechnungsId)
    {
        if (!$belegId || !$abrechnungsTyp || !$abrechnungsId) {
            log_message('error', 'entferneZuordnung: Unvollständige Parameter');
            return false;
        }

        try {
            // Debugging: Prüfe ob Zuordnung existiert
            $existiert = $this->where([
                'beleg_id' => $belegId,
                'abrechnung_typ' => $abrechnungsTyp,
                'abrechnung_id' => $abrechnungsId
            ])->countAllResults();

            if ($existiert === 0) {
                log_message('warning', "Zuordnung nicht gefunden: Beleg {$belegId}, Typ {$abrechnungsTyp}, Abrechnung {$abrechnungsId}");
                return false;
            }

            // Zuordnung löschen
            $deleted = $this->where([
                'beleg_id' => $belegId,
                'abrechnung_typ' => $abrechnungsTyp,
                'abrechnung_id' => $abrechnungsId
            ])->delete();

            if ($deleted) {
                // Prüfe ob Beleg in anderen Abrechnungen ist
                $andereZuordnungen = $this->where('beleg_id', $belegId)->countAllResults();

                if ($andereZuordnungen === 0) {
                    // Beleg ist in keiner anderen Abrechnung, Status zurücksetzen
                    $this->aktualisiereBeregStatus($belegId, 'erfasst');
                }

                log_message('info', "Beleg {$belegId} erfolgreich aus {$abrechnungsTyp}-Abrechnung {$abrechnungsId} entfernt");
                return true;
            }

            return false;

        } catch (\Exception $e) {
            log_message('error', 'Fehler in entferneZuordnung: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt alle Abrechnungen für einen bestimmten Beleg
     */
    public function getAbrechnungenFuerBeleg($belegId)
    {
        $abrechnungen = [];

        // AH² Abrechnungen
        $ahAbrechnungen = $this->select('
                abrechnung_belege.hinzugefuegt_am,
                ah_abrechnungen.id,
                ah_abrechnungen.titel,
                ah_abrechnungen.abrechnungsmonat,
                ah_abrechnungen.status,
                "ah" as typ
            ')
            ->join('ah_abrechnungen', 'ah_abrechnungen.id = abrechnung_belege.abrechnung_id')
            ->where('abrechnung_belege.beleg_id', $belegId)
            ->where('abrechnung_belege.abrechnung_typ', 'ah')
            ->findAll();

        // HV Abrechnungen
        $hvAbrechnungen = $this->select('
                abrechnung_belege.hinzugefuegt_am,
                hv_abrechnungen.id,
                hv_abrechnungen.titel,
                hv_abrechnungen.abrechnungsmonat,
                hv_abrechnungen.status,
                "hv" as typ
            ')
            ->join('hv_abrechnungen', 'hv_abrechnungen.id = abrechnung_belege.abrechnung_id')
            ->where('abrechnung_belege.beleg_id', $belegId)
            ->where('abrechnung_belege.abrechnung_typ', 'hv')
            ->findAll();

        return array_merge($ahAbrechnungen, $hvAbrechnungen);
    }

    /**
     * Holt die Abrechnungen für mehrere Belege auf einmal (2 Queries statt 2·N).
     *
     * @param int[] $belegIds
     * @return array<int, array> Map beleg_id => Liste der Abrechnungen
     */
    public function getAbrechnungenFuerBelege(array $belegIds): array
    {
        if (empty($belegIds)) {
            return [];
        }

        $ahAbrechnungen = $this->select('
                abrechnung_belege.beleg_id,
                abrechnung_belege.hinzugefuegt_am,
                ah_abrechnungen.id,
                ah_abrechnungen.titel,
                ah_abrechnungen.abrechnungsmonat,
                ah_abrechnungen.status,
                "ah" as typ
            ')
            ->join('ah_abrechnungen', 'ah_abrechnungen.id = abrechnung_belege.abrechnung_id')
            ->whereIn('abrechnung_belege.beleg_id', $belegIds)
            ->where('abrechnung_belege.abrechnung_typ', 'ah')
            ->findAll();

        $hvAbrechnungen = $this->select('
                abrechnung_belege.beleg_id,
                abrechnung_belege.hinzugefuegt_am,
                hv_abrechnungen.id,
                hv_abrechnungen.titel,
                hv_abrechnungen.abrechnungsmonat,
                hv_abrechnungen.status,
                "hv" as typ
            ')
            ->join('hv_abrechnungen', 'hv_abrechnungen.id = abrechnung_belege.abrechnung_id')
            ->whereIn('abrechnung_belege.beleg_id', $belegIds)
            ->where('abrechnung_belege.abrechnung_typ', 'hv')
            ->findAll();

        $map = [];
        foreach (array_merge($ahAbrechnungen, $hvAbrechnungen) as $zeile) {
            $map[$zeile['beleg_id']][] = $zeile;
        }

        return $map;
    }

    /**
     * Prüft ob ein Beleg bereits in einer bestimmten Abrechnung ist
     */
    public function istBelegInAbrechnung($belegId, $abrechnungsTyp, $abrechnungsId)
    {
        return $this->where([
                'beleg_id' => $belegId,
                'abrechnung_typ' => $abrechnungsTyp,
                'abrechnung_id' => $abrechnungsId
            ])->countAllResults() > 0;
    }

    /**
     * Prüft ob ein Beleg bereits in anderen Abrechnungen desselben Typs ist
     */
    public function istBelegInAnderenAbrechnungen($belegId, $abrechnungsTyp, $excludeAbrechnungsId = null)
    {
        $builder = $this->where('beleg_id', $belegId)
            ->where('abrechnung_typ', $abrechnungsTyp);

        if ($excludeAbrechnungsId) {
            $builder->where('abrechnung_id !=', $excludeAbrechnungsId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Prüft ob ein Beleg für einen Abrechnungstyp berechtigt ist
     */
    private function istBelegBerechtigt($belegId, $abrechnungsTyp)
    {
        $belegModel = new \App\Models\BelegModel();
        $beleg = $belegModel->find($belegId);

        if (!$beleg) {
            return false;
        }

        $erforderlicheKategorie = $abrechnungsTyp === 'ah' ? 'ah_berechtigt' : 'hv_berechtigt';

        return $beleg['kategorie'] === $erforderlicheKategorie;
    }

    /**
     * Prüft ob eine Abrechnung existiert
     */
    private function abrechnungExistiert($abrechnungsTyp, $abrechnungsId)
    {
        if ($abrechnungsTyp === 'ah') {
            $model = new \App\Models\AhAbrechnungModel();
        } else {
            $model = new \App\Models\HvAbrechnungModel();
        }

        return $model->find($abrechnungsId) !== null;
    }

    /**
     * Aktualisiert den Status eines Belegs
     */
    private function aktualisiereBeregStatus($belegId, $neuerStatus)
    {
        $belegModel = new \App\Models\BelegModel();
        return $belegModel->update($belegId, ['status' => $neuerStatus]);
    }

    /**
     * Löscht alle Zuordnungen einer Abrechnung
     */
    public function loescheAlleZuordnungen($abrechnungsTyp, $abrechnungsId)
    {
        // Hole alle betroffenen Belege
        $belege = $this->select('beleg_id')
            ->where([
                'abrechnung_typ' => $abrechnungsTyp,
                'abrechnung_id' => $abrechnungsId
            ])->findAll();

        $belegIds = array_column($belege, 'beleg_id');

        // Lösche Zuordnungen
        $deleted = $this->where([
            'abrechnung_typ' => $abrechnungsTyp,
            'abrechnung_id' => $abrechnungsId
        ])->delete();

        if ($deleted && !empty($belegIds)) {
            // Setze Status der Belege zurück, falls sie in keinen anderen Abrechnungen sind
            foreach ($belegIds as $belegId) {
                $andereZuordnungen = $this->where('beleg_id', $belegId)->countAllResults();
                if ($andereZuordnungen === 0) {
                    $this->aktualisiereBeregStatus($belegId, 'erfasst');
                }
            }
        }

        return $deleted;
    }

    /**
     * Holt verfügbare Belege für eine Abrechnung
     * (Belege die nicht bereits in einer Abrechnung desselben Typs sind)
     */
    public function getVerfuegbareBelege($abrechnungsTyp, $excludeAbrechnungsId = null)
    {
        $erforderlicheKategorie = $abrechnungsTyp === 'ah' ? 'ah_berechtigt' : 'hv_berechtigt';

        // Subquery für bereits zugeordnete Belege
        $db = \Config\Database::connect();
        $subqueryBuilder = $db->table('abrechnung_belege');
        $subqueryBuilder->select('beleg_id')
            ->where('abrechnung_typ', $abrechnungsTyp);


        $subquery = $subqueryBuilder->getCompiledSelect();

        // Hauptquery für verfügbare Belege
        $builder = $db->table('belege');
        return $builder->select('id, belegnummer, rechnungsdatum, beschreibung, betrag, lieferant, status')
            ->where('kategorie', $erforderlicheKategorie)
            ->where('status', 'erfasst') // Nur erfasste Belege, nicht "in_abrechnung"
            ->where("id NOT IN ($subquery)", null, false)
            ->orderBy('rechnungsdatum', 'DESC') // Neueste zuerst
            ->get()
            ->getResultArray();
    }

    /**
     * Holt bereits zugeordnete Belege für eine Abrechnung
     */
    public function getZugeordneteBelege($abrechnungsTyp, $abrechnungsId)
    {
        return $this->select('
                belege.id, 
                belege.belegnummer, 
                belege.rechnungsdatum, 
                belege.beschreibung, 
                belege.betrag, 
                belege.lieferant,
                abrechnung_belege.hinzugefuegt_am
            ')
            ->join('belege', 'belege.id = abrechnung_belege.beleg_id')
            ->where('abrechnung_belege.abrechnung_typ', $abrechnungsTyp)
            ->where('abrechnung_belege.abrechnung_id', $abrechnungsId)
            ->orderBy('belege.rechnungsdatum', 'ASC')
            ->findAll();
    }
}