<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * GetraenkeVersandModel - Versand-Log der Einzelrechnungen (Issue #35)
 *
 * Ein Eintrag pro erfolgreich verschickter E-Mail. Dient der Anzeige
 * "verschickt am" auf der Versand-Seite (Doppelversand-Schutz per Default
 * abgewählter Checkbox — bewusst keine harte Sperre).
 */
class GetraenkeVersandModel extends Model
{
    protected $table = 'getraenke_versand';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['monat', 'person', 'email', 'gesendet_am'];

    /**
     * Loggt einen erfolgreichen Versand.
     */
    public function logVersand(string $monat, string $person, string $email): void
    {
        $this->insert([
            'monat' => $monat,
            'person' => $person,
            'email' => $email,
            'gesendet_am' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Letzter Versand pro Person für einen Monat (map lower(person) => datetime)
     *
     * @return array<string, string>
     */
    public function getVersendetFuerMonat(string $monat): array
    {
        $zeilen = $this->where('monat', $monat)
            ->orderBy('gesendet_am', 'ASC')
            ->findAll();

        $map = [];
        foreach ($zeilen as $zeile) {
            $map[person_schluessel($zeile['person'])] = $zeile['gesendet_am'];
        }

        return $map;
    }
}
