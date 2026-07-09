<?php

namespace App\Controllers;

use App\Models\AhAbrechnungModel;

/**
 * AhAbrechnungenController - AH² Abrechnungen
 *
 * Die gesamte Logik liegt im AbstractAbrechnungenController.
 */
class AhAbrechnungenController extends AbstractAbrechnungenController
{
    public function __construct()
    {
        parent::__construct();

        $this->typ = 'ah';
        $this->typName = 'AH²';
        $this->abrechnungModel = new AhAbrechnungModel();
    }
}
