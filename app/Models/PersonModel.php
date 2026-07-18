<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PersonModel — Personen-/Mitglieder-Register (Issue #61, Fundament für #59)
 *
 * Autoritatives Namens- und E-Mail-Register. Löst die bisherige Freitext-
 * Repräsentation ab (Registry + Soft-Link): schulden verweist per nullable
 * person_id hierher, behält aber den Anzeigenamen als String.
 *
 * Cross-Tabellen-Matching läuft AUSSCHLIESSLICH über person_schluessel()
 * (whitespace-/case-normalisiert) — hier zentralisiert, damit das frühere
 * Nebeneinander aus DB-Kollation (accent-insensitiv) und PHP-Schlüssel
 * (accent-sensitiv) vereinheitlicht ist.
 */
class PersonModel extends Model
{
    protected $table = 'persons';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;

    protected $allowedFields = ['vorname', 'nachname', 'email', 'aktiv'];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'vorname' => 'permit_empty|max_length[100]',
        'nachname' => 'required|min_length[2]|max_length[100]',
        'email' => 'permit_empty|valid_email|max_length[254]',
    ];

    protected $validationMessages = [
        'nachname' => [
            'required' => 'Bitte geben Sie einen Nachnamen an.',
            'min_length' => 'Der Nachname muss mindestens 2 Zeichen lang sein.',
        ],
        'email' => [
            'valid_email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ],
    ];

    /**
     * Anzeigename einer Person: „Vorname Nachname" bzw. nur der vorhandene Teil.
     */
    public static function anzeigename(array $person): string
    {
        $teile = trim((string) ($person['vorname'] ?? '') . ' ' . (string) ($person['nachname'] ?? ''));

        return person_normalisiere($teile);
    }

    /**
     * Pure, DB-los testbarer Seam: baut aus Personen-Zeilen die Match-Map
     * person_schluessel(anzeigename) => Person. Bei Schlüsselkollision gewinnt
     * die erste Zeile (findAll()-Reihenfolge, ORDER BY nachname/vorname).
     *
     * @param array<array> $persons
     * @return array<string, array>
     */
    public static function baueSchluesselMap(array $persons): array
    {
        $map = [];
        foreach ($persons as $person) {
            $schluessel = person_schluessel(self::anzeigename($person));
            if ($schluessel !== '' && !isset($map[$schluessel])) {
                $map[$schluessel] = $person;
            }
        }

        return $map;
    }

    /**
     * Alle Personen als Match-Map person_schluessel => Person.
     *
     * @return array<string, array>
     */
    public function alleMitSchluessel(): array
    {
        return self::baueSchluesselMap(
            $this->orderBy('nachname')->orderBy('vorname')->findAll()
        );
    }

    /**
     * Soft-Link-Auflösung: person_id zu einem Freitext-Namen (NULL = kein Treffer,
     * z.B. Gäste oder Institutionen).
     */
    public function findIdFuerName(string $name): ?int
    {
        return self::idAusMap($this->alleMitSchluessel(), $name);
    }

    /**
     * Pure Auflösung eines Namens gegen eine Schlüssel-Map (DB-los testbar).
     *
     * @param array<string, array> $map
     */
    public static function idAusMap(array $map, string $name): ?int
    {
        $person = $map[person_schluessel($name)] ?? null;

        return $person ? (int) $person['id'] : null;
    }

    /**
     * E-Mail-Adressen zu den übergebenen Namen (map person_schluessel => email).
     * Ersetzt PersonEmailModel::findEmailsFuer (gleiche Rückgabeform); nur
     * Personen mit hinterlegter Adresse landen in der Map.
     *
     * @param array<string> $namen
     * @return array<string, string>
     */
    public function findEmailsFuer(array $namen): array
    {
        if ($namen === []) {
            return [];
        }

        return self::emailsAusMap($this->alleMitSchluessel(), $namen);
    }

    /**
     * Pure Filterung der Schlüssel-Map auf die gesuchten Namen mit Adresse
     * (DB-los testbar).
     *
     * @param array<string, array> $map
     * @param array<string> $namen
     * @return array<string, string>
     */
    public static function emailsAusMap(array $map, array $namen): array
    {
        $gesucht = [];
        foreach ($namen as $name) {
            $gesucht[person_schluessel($name)] = true;
        }

        $ergebnis = [];
        foreach ($map as $schluessel => $person) {
            if (isset($gesucht[$schluessel]) && !empty($person['email'])) {
                $ergebnis[$schluessel] = $person['email'];
            }
        }

        return $ergebnis;
    }

    /**
     * Anzeigenamen aller aktiven Personen für die Datalists.
     *
     * @return array<string>
     */
    public function getAnzeigenamen(): array
    {
        $namen = [];
        foreach ($this->where('aktiv', 1)->orderBy('nachname')->orderBy('vorname')->findAll() as $person) {
            $name = self::anzeigename($person);
            if ($name !== '') {
                $namen[] = $name;
            }
        }

        return $namen;
    }

    /**
     * Alle Personen für die Verwaltungsseite (sortiert nach Nachname/Vorname).
     *
     * @return array
     */
    public function getAlle(): array
    {
        return $this->orderBy('nachname')->orderBy('vorname')->findAll();
    }

    /**
     * Findet eine Person per Schlüssel oder legt sie neu an (nachname = Name),
     * und setzt die E-Mail-Adresse. Gibt die person_id zurück, oder null bei
     * Validierungsfehler. Genutzt vom Rechnungsversand (Adresse zum Freitext-
     * Namen speichern) und der Personen-Verwaltung.
     */
    public function upsertFuerName(string $name, ?string $email): ?int
    {
        $name = person_normalisiere($name);
        if ($name === '') {
            return null;
        }

        $email = $email !== null ? trim($email) : null;
        $vorhanden = $this->alleMitSchluessel()[person_schluessel($name)] ?? null;

        if ($vorhanden) {
            if ($email !== null && $email !== '' && ($vorhanden['email'] ?? null) !== $email) {
                $this->update($vorhanden['id'], ['email' => $email]);
            }

            return (int) $vorhanden['id'];
        }

        $id = $this->insert([
            'nachname' => $name,
            'email' => ($email === '' ? null : $email),
        ]);

        return $id ? (int) $id : null;
    }
}
