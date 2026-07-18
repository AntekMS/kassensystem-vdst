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
    /**
     * Grund-Marker für 1-Klick-Getränkeausgleiche (Issue #43) —
     * identifiziert die Einträge zusammen mit istGetraenkeAusgleich().
     */
    public const GETRAENKE_BEGLICHEN_GRUND = 'Getränkerechnung beglichen';

    /**
     * Institutions-"Personen" der Abrechnungs-Forderungen (Issue #38) —
     * keine echten Personen, daher nie ins Personen-Register (Issue #58).
     */
    public const INSTITUTION_PERSONEN = ['ah' => 'AH²-Bund', 'hv' => 'Heimverein'];

    /**
     * Ist der Freitext-Name eine Institutions-Zeile (AH²-Bund/Heimverein)?
     * Vergleich wie überall über person_schluessel().
     */
    public static function istInstitution(string $person): bool
    {
        $schluessel = person_schluessel($person);

        foreach (self::INSTITUTION_PERSONEN as $institution) {
            if ($schluessel === person_schluessel($institution)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grund der importierten Getränke-Forderungen eines Monats (Issue #35).
     *
     * Einziger Codepfad für diesen String — Abfragen matchen ihn IMMER exakt,
     * nie per LIKE-Prefix: GETRAENKE_BEGLICHEN_GRUND beginnt gleich.
     */
    public static function getraenkeImportGrund(string $monatsName): string
    {
        return 'Getränkerechnung ' . $monatsName;
    }

    protected $table = 'schulden';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'person', 'person_id', 'typ', 'kategorie', 'datum', 'grund', 'betrag',
        'beleg_id', 'buchung_id', 'abrechnung_typ', 'abrechnung_id'
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
     * Sortier-Optionen der Personen-Übersicht (Wert => ORDER BY).
     * Whitelist — GET-Parameter dürfen nie roh ins ORDER BY.
     */
    public const SORTIERUNGEN = [
        'person' => ['person', 'ASC'],
        'getraenke' => ['forderungen_getraenke', 'DESC'],
        'forderungen' => ['forderungen_gesamt', 'DESC'],
        'verbindlichkeiten' => ['verbindlichkeiten_gesamt', 'DESC'],
        'letzter_eintrag' => ['letzter_eintrag', 'DESC'],
    ];

    /**
     * Übersicht: eine Zeile pro Person mit offenen Summen
     *
     * $filter: suche (Name-Substring), status (offene_getraenke |
     * offene_forderungen | verbindlichkeiten | getraenkestopp | ausgeglichen),
     * sortierung (Key aus SORTIERUNGEN). Status filtert auf den aggregierten
     * Summen → HAVING, nicht WHERE.
     *
     * @return array
     */
    public function getPersonenUebersicht(array $filter = [])
    {
        $builder = $this->select("
                person,
                SUM(CASE WHEN typ = 'forderung' THEN betrag ELSE 0 END) AS forderungen_gesamt,
                SUM(CASE WHEN typ = 'forderung' AND kategorie = 'getraenke' THEN betrag ELSE 0 END) AS forderungen_getraenke,
                SUM(CASE WHEN typ = 'verbindlichkeit' THEN betrag ELSE 0 END) AS verbindlichkeiten_gesamt,
                COUNT(*) AS anzahl,
                MAX(datum) AS letzter_eintrag
            ")
            ->groupBy('person');

        if (!empty($filter['suche'])) {
            $builder->like('person', $filter['suche']);
        }

        switch ($filter['status'] ?? '') {
            case 'offene_getraenke':
                $builder->having('forderungen_getraenke >=', 0.01);
                break;
            case 'offene_forderungen':
                $builder->having('forderungen_gesamt >=', 0.01);
                break;
            case 'verbindlichkeiten':
                $builder->having('verbindlichkeiten_gesamt >=', 0.01);
                break;
            case 'getraenkestopp':
                $builder->having('forderungen_getraenke >=', GETRAENKESTOPP_LIMIT);
                break;
            case 'ausgeglichen':
                $builder->having('forderungen_gesamt <', 0.01)
                    ->having('verbindlichkeiten_gesamt <', 0.01);
                break;
        }

        [$spalte, $richtung] = self::SORTIERUNGEN[$filter['sortierung'] ?? ''] ?? self::SORTIERUNGEN['person'];

        return $builder->orderBy($spalte, $richtung)
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
     * Soft-Link zum Personen-Register (Issue #61): person_id zu einem Freitext-
     * Namen, oder null (Gäste/Institutionen/kein Registereintrag). Zentral hier,
     * damit alle Insert-/Sync-Pfade person_id konsistent mitschreiben; die
     * Aggregation/Detailseite bleiben bewusst namensbasiert.
     */
    private function personId(string $name): ?int
    {
        return (new PersonModel())->findIdFuerName($name);
    }

    /**
     * Eintrag stammt aus einer Quelle (Beleg/Buchung/Abrechnung) und wird
     * automatisch verwaltet — manuelles Bearbeiten/Löschen ist gesperrt.
     */
    public static function istAutomatisch(array $eintrag): bool
    {
        return !empty($eintrag['beleg_id'])
            || !empty($eintrag['buchung_id'])
            || !empty($eintrag['abrechnung_id']);
    }

    /**
     * Synchronisiert die automatische Verbindlichkeit zu einem Beleg (Issue #38).
     *
     * erstattung_person gesetzt → Eintrag anlegen bzw. aktualisieren,
     * leer → Eintrag entfernen. Ausgleichs-Einträge hängen an der Buchung
     * (buchung_id), nicht am Beleg, und bleiben daher unberührt.
     */
    public function syncBelegVerbindlichkeit(array $beleg): void
    {
        $vorhanden = $this->where('beleg_id', $beleg['id'])->first();
        $person = trim((string) ($beleg['erstattung_person'] ?? ''));

        if ($person === '') {
            if ($vorhanden) {
                $this->delete($vorhanden['id']);
            }
            return;
        }

        $daten = [
            'person' => $person,
            'person_id' => $this->personId($person),
            'typ' => 'verbindlichkeit',
            'kategorie' => 'sonstige',
            'datum' => $beleg['rechnungsdatum'],
            'grund' => mb_substr('Beleg ' . $beleg['belegnummer'] . ': ' . $beleg['beschreibung'], 0, 255),
            'betrag' => $beleg['betrag'],
            'beleg_id' => $beleg['id'],
        ];

        if ($vorhanden) {
            $this->update($vorhanden['id'], $daten);
        } else {
            $this->insert($daten);
        }
    }

    /**
     * Synchronisiert die Einträge zu einer Abrechnung (Issue #38), idempotent:
     * - eingereicht/bezahlt → Forderung gegen AH²-Bund bzw. Heimverein
     * - bezahlt → zusätzlich negativer Ausgleichs-Eintrag
     * - Zurückstufen entfernt die jeweiligen Einträge wieder
     *
     * Forderung und Ausgleich werden am Vorzeichen unterschieden.
     */
    public function syncAbrechnungForderung(string $typ, array $abrechnung): void
    {
        $eintraege = $this->where('abrechnung_typ', $typ)
            ->where('abrechnung_id', $abrechnung['id'])
            ->findAll();

        $forderung = null;
        $ausgleich = null;
        foreach ($eintraege as $eintrag) {
            if ((float) $eintrag['betrag'] >= 0) {
                $forderung = $eintrag;
            } else {
                $ausgleich = $eintrag;
            }
        }

        $summe = (float) $abrechnung['gesamtsumme'];
        $sollForderung = $summe > 0 && in_array($abrechnung['status'], ['eingereicht', 'bezahlt'], true);
        $sollAusgleich = $summe > 0 && $abrechnung['status'] === 'bezahlt';

        $basis = [
            'person' => self::INSTITUTION_PERSONEN[$typ],
            'typ' => 'forderung',
            'kategorie' => 'abrechnung',
            'abrechnung_typ' => $typ,
            'abrechnung_id' => $abrechnung['id'],
        ];
        $titel = mb_substr($abrechnung['titel'], 0, 220);

        if ($sollForderung) {
            $daten = $basis + [
                'datum' => $abrechnung['eingereicht_am'] ?? date('Y-m-d'),
                'grund' => 'Abrechnung eingereicht: ' . $titel,
                'betrag' => $summe,
            ];
            $forderung ? $this->update($forderung['id'], $daten) : $this->insert($daten);
        } elseif ($forderung) {
            $this->delete($forderung['id']);
        }

        if ($sollAusgleich) {
            $daten = $basis + [
                'datum' => $abrechnung['bezahlt_am'] ?? date('Y-m-d'),
                'grund' => 'Abrechnung bezahlt: ' . $titel,
                'betrag' => -$summe,
            ];
            $ausgleich ? $this->update($ausgleich['id'], $daten) : $this->insert($daten);
        } elseif ($ausgleich) {
            $this->delete($ausgleich['id']);
        }
    }

    /**
     * Legt den Ausgleichs-Eintrag zu einer Buchung an (Issue #38).
     *
     * Einnahme → Person zahlt an den Verein (Forderung sinkt),
     * Ausgabe → Verein zahlt an die Person (Verbindlichkeit sinkt).
     * Der Betrag ist daher immer negativ (= Rückzahlung).
     */
    public function erstelleBuchungsAusgleich(array $buchung, string $person, string $kategorie): void
    {
        $this->insert([
            'person' => $person,
            'person_id' => $this->personId($person),
            'typ' => $buchung['buchungsart'] === 'einnahme' ? 'forderung' : 'verbindlichkeit',
            'kategorie' => $kategorie,
            'datum' => $buchung['buchungsdatum'],
            'grund' => mb_substr('Ausgleich per Buchung: ' . $buchung['beschreibung'], 0, 255),
            'betrag' => -abs((float) $buchung['betrag']),
            'buchung_id' => $buchung['id'],
        ]);
    }

    /**
     * Hält den Ausgleichs-Eintrag nach einer Buchungs-Änderung aktuell.
     */
    public function syncBuchungAusgleich(array $buchung): void
    {
        $vorhanden = $this->where('buchung_id', $buchung['id'])->first();

        if (!$vorhanden) {
            return;
        }

        $this->update($vorhanden['id'], [
            'typ' => $buchung['buchungsart'] === 'einnahme' ? 'forderung' : 'verbindlichkeit',
            'datum' => $buchung['buchungsdatum'],
            'grund' => mb_substr('Ausgleich per Buchung: ' . $buchung['beschreibung'], 0, 255),
            'betrag' => -abs((float) $buchung['betrag']),
        ]);
    }

    /**
     * Eintrag ist ein 1-Klick-Getränkeausgleich (Issue #43).
     */
    public static function istGetraenkeAusgleich(array $eintrag): bool
    {
        return $eintrag['typ'] === 'forderung'
            && $eintrag['kategorie'] === 'getraenke'
            && (float) $eintrag['betrag'] < 0
            && $eintrag['grund'] === self::GETRAENKE_BEGLICHEN_GRUND
            && !self::istAutomatisch($eintrag);
    }

    /**
     * Offene Getränke-Forderung einer Person (Rückzahlungen sind negativ → SUM).
     */
    public function offeneGetraenkeForderung(string $person): float
    {
        $zeile = $this->selectSum('betrag', 'summe')
            ->where('person', $person)
            ->where('typ', 'forderung')
            ->where('kategorie', 'getraenke')
            ->get()
            ->getRowArray();

        return (float) ($zeile['summe'] ?? 0);
    }

    /**
     * Legt den 1-Klick-Getränkeausgleich an (Issue #43).
     *
     * Bewusst ohne Quell-IDs: der Eintrag bleibt manuell löschbar.
     */
    public function erstelleGetraenkeAusgleich(string $person, float $betrag): void
    {
        $this->insert([
            'person' => $person,
            'person_id' => $this->personId($person),
            'typ' => 'forderung',
            'kategorie' => 'getraenke',
            'datum' => date('Y-m-d'),
            'grund' => self::GETRAENKE_BEGLICHEN_GRUND,
            'betrag' => -abs($betrag),
        ]);
    }

    /**
     * Alle Personen mit offener Getränke-Forderung, aggregiert (Issue #55).
     *
     * Gleiche Vorzeichen-Semantik wie offeneGetraenkeForderung() (Rückzahlungen
     * negativ → SUM); nur Personen mit echtem Restbetrag (>= 0,01 €). Als
     * eigene Methode ausgelagert, damit begleicheAlleGetraenke() ohne DB
     * getestet werden kann.
     *
     * @return array<array{person: string, summe: string}>
     */
    public function getOffeneGetraenkeForderungen(): array
    {
        return $this->select('person, SUM(betrag) AS summe')
            ->where('typ', 'forderung')
            ->where('kategorie', 'getraenke')
            ->groupBy('person')
            ->having('SUM(betrag) >=', 0.01)
            ->orderBy('person')
            ->findAll();
    }

    /**
     * Gleicht die offenen Getränke-Forderungen ALLER Personen aus (Issue #55).
     *
     * Iteriert über jede Person mit offenem Getränke-Restbetrag und legt je
     * Person denselben 1-Klick-Ausgleich an wie der Einzel-Button
     * (erstelleGetraenkeAusgleich — voller offener Betrag, negativ).
     *
     * Ganz-oder-gar-nicht: der Ausgleichs-Batch läuft in einer Transaktion,
     * damit ein Teilausfall keine inkonsistenten Ausgleiche hinterlässt.
     *
     * @return int Anzahl der beglichenen Personen
     */
    public function begleicheAlleGetraenke(): int
    {
        $this->db->transStart();

        $anzahl = $this->erstelleAlleGetraenkeAusgleiche();

        $this->db->transComplete();

        return $anzahl;
    }

    /**
     * Reiner Ausgleichs-Loop ohne Transaktions-Boilerplate — als eigener,
     * DB-los testbarer Seam ausgelagert (die DB-Zugriffe stecken in
     * getOffeneGetraenkeForderungen() und erstelleGetraenkeAusgleich()).
     *
     * @return int Anzahl der beglichenen Personen
     */
    protected function erstelleAlleGetraenkeAusgleiche(): int
    {
        $anzahl = 0;

        foreach ($this->getOffeneGetraenkeForderungen() as $person) {
            $this->erstelleGetraenkeAusgleich($person['person'], (float) $person['summe']);
            $anzahl++;
        }

        return $anzahl;
    }

    /**
     * Neuester Getränke-Eintrag einer Person, sofern er ein 1-Klick-Ausgleich
     * ist — sonst null. Nur dann wird das 1-Klick-Undo angeboten.
     */
    public function letzterGetraenkeAusgleich(string $person): ?array
    {
        $eintrag = $this->where('person', $person)
            ->where('kategorie', 'getraenke')
            ->orderBy('datum', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return ($eintrag && self::istGetraenkeAusgleich($eintrag)) ? $eintrag : null;
    }

    /**
     * Importierte Getränke-Forderungen eines Monats, aggregiert pro Person
     * (Issue #35) — kanonische Datenquelle für Übersichts-PDF und
     * Versand-Seite; jederzeit re-derivierbar, keine Session nötig.
     *
     * GROUP BY macht die Liste robust gegen (erlaubten) Doppelimport.
     *
     * @return array<array{person: string, betrag: string}>
     */
    public function getImportForderungen(string $monatsName)
    {
        return $this->select('person, SUM(betrag) AS betrag')
            ->where('grund', self::getraenkeImportGrund($monatsName))
            ->where('typ', 'forderung')
            ->where('kategorie', 'getraenke')
            ->groupBy('person')
            ->having('SUM(betrag) >=', 0.01)
            ->orderBy('person')
            ->findAll();
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
