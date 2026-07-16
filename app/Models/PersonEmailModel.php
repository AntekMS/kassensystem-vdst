<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PersonEmailModel - E-Mail-Adressen der Personen (Issue #35)
 *
 * Zuordnung Freitext-Name → E-Mail für den Rechnungsversand. Namen werden
 * wie in der Schuldenliste getrimmt und case-insensitiv gematcht
 * (UNIQUE-Key über die DB-Kollation, Matching im PHP über mb_strtolower).
 */
class PersonEmailModel extends Model
{
    protected $table = 'person_emails';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['name', 'email'];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'email' => 'required|valid_email|max_length[254]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Bitte geben Sie eine Person an.',
            'min_length' => 'Der Name muss mindestens 2 Zeichen lang sein.',
        ],
        'email' => [
            'required' => 'Die E-Mail-Adresse ist erforderlich.',
            'valid_email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        ],
    ];

    /**
     * Alle Zuordnungen für die Verwaltungsseite
     *
     * @return array
     */
    public function getAlle()
    {
        return $this->orderBy('name')->findAll();
    }

    /**
     * E-Mail-Adressen zu den übergebenen Namen (map lower(name) => email)
     *
     * @param array<string> $namen
     * @return array<string, string>
     */
    public function findEmailsFuer(array $namen): array
    {
        if ($namen === []) {
            return [];
        }

        // Schreib- und Lesepfad müssen identisch normalisieren, sonst finden
        // sich Namen mit Doppel-Leerzeichen nicht wieder (upsertEmail kollabiert
        // Whitespace vor dem Speichern).
        $normal = array_values(array_unique(array_map('person_normalisiere', $namen)));

        $map = [];
        foreach ($this->whereIn('name', $normal)->findAll() as $zeile) {
            $map[person_schluessel($zeile['name'])] = $zeile['email'];
        }

        return $map;
    }

    /**
     * Legt die Zuordnung an bzw. aktualisiert sie (Name case-insensitiv
     * über die DB-Kollation). Gibt false bei Validierungsfehlern zurück.
     */
    public function upsertEmail(string $name, string $email): bool
    {
        $name = person_normalisiere($name);
        $email = trim($email);

        $vorhanden = $this->where('name', $name)->first();

        if ($vorhanden) {
            if ($vorhanden['email'] === $email) {
                return true;
            }

            return (bool) $this->update($vorhanden['id'], ['name' => $vorhanden['name'], 'email' => $email]);
        }

        return (bool) $this->insert(['name' => $name, 'email' => $email]);
    }
}
