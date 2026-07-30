<?php

namespace App\Models;

/**
 * AhAbrechnungModel - AH² Abrechnungen
 *
 * Konfigurations-Stub: die gesamte Logik liegt in AbstractAbrechnungModel.
 */
class AhAbrechnungModel extends AbstractAbrechnungModel
{
    protected $table = 'ah_abrechnungen';

    protected string $typ = 'ah';
    protected string $typLabel = 'AH²';
    protected string $berechtigtKategorie = 'ah_berechtigt';
}
