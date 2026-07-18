<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * GetraenkeVersandModel - Versand-Log der Einzelrechnungen (Issue #35)
 *
 * Ein Eintrag pro erfolgreich verschickter E-Mail. Dient der Anzeige
 * "verschickt am" auf der Versand-Seite (Doppelversand-Schutz per Default
 * abgewählter Checkbox — bewusst keine harte Sperre).
 *
 * Gekeyt auf monat + monat_bis mit derselben Zeitraum-Semantik wie die
 * Import-Marker (Issue #64): Einzelmonat- und Zeitraum-Versand sind
 * getrennte Identitäten, ein November-Versand markiert auf der
 * Nov–Dez-Seite niemanden als „verschickt". Normalisierung bis === von →
 * NULL über SchuldModel::importMonatBis.
 */
class GetraenkeVersandModel extends Model
{
    protected $table = 'getraenke_versand';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['monat', 'monat_bis', 'person', 'email', 'gesendet_am'];

    /**
     * Loggt einen erfolgreichen Versand.
     */
    public function logVersand(string $monat, ?string $monatBis, string $person, string $email): void
    {
        $this->insert([
            'monat' => $monat,
            'monat_bis' => SchuldModel::importMonatBis($monat, $monatBis),
            'person' => $person,
            'email' => $email,
            'gesendet_am' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Letzter Versand pro Person für einen Monat bzw. Zeitraum
     * (map person_schluessel => datetime)
     *
     * @return array<string, string>
     */
    public function getVersendetFuerMonat(string $monat, ?string $monatBis = null): array
    {
        $zeilen = $this->where('monat', $monat)
            ->where('monat_bis', SchuldModel::importMonatBis($monat, $monatBis))
            ->orderBy('gesendet_am', 'ASC')
            ->findAll();

        $map = [];
        foreach ($zeilen as $zeile) {
            $map[person_schluessel($zeile['person'])] = $zeile['gesendet_am'];
        }

        return $map;
    }
}
