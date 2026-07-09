<?php

namespace App\Controllers;

use App\Models\HvAbrechnungModel;

/**
 * HvAbrechnungenController - Heimverein Abrechnungen
 *
 * Die gesamte Logik liegt im AbstractAbrechnungenController;
 * HV-spezifisch ist nur das Begründungsfeld (über $typ gesteuert).
 */
class HvAbrechnungenController extends AbstractAbrechnungenController
{
    public function __construct()
    {
        parent::__construct();

        $this->typ = 'hv';
        $this->typName = 'HV';
        $this->abrechnungModel = new HvAbrechnungModel();
    }
}
