<?php

namespace App\Models;

use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

/**
 * HvAbrechnungModel - Heimverein Abrechnungen
 *
 * Konfigurations-Stub: die gesamte Logik liegt in AbstractAbrechnungModel.
 * Einziger fachlicher Unterschied zu AH² ist das Freitext-Feld `begruendung`,
 * das die Excel-/PDF-Exporte zusätzlich ausgeben.
 */
class HvAbrechnungModel extends AbstractAbrechnungModel
{
    protected $table = 'hv_abrechnungen';

    protected string $typ = 'hv';
    protected string $typLabel = 'HV';
    protected string $berechtigtKategorie = 'hv_berechtigt';

    public function __construct(?ConnectionInterface &$db = null, ?ValidationInterface $validation = null)
    {
        // Nur HV hat die Freitext-Begründung — der Rest der Regeln kommt aus der Basis.
        $this->allowedFields[] = 'begruendung';
        $this->validationRules['begruendung'] = 'max_length[1000]';

        parent::__construct($db, $validation);
    }
}
