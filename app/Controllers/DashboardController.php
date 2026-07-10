<?php

namespace App\Controllers;

use App\Models\AhAbrechnungModel;
use App\Models\BelegModel;
use App\Models\BuchungModel;
use App\Models\HvAbrechnungModel;
use App\Models\SchuldModel;

/**
 * DashboardController - Zentrale Übersicht
 *
 * Kontostände, Kennzahlen und die neuesten Buchungen/Belege.
 */
class DashboardController extends BaseController
{
    protected $belegModel;
    protected $buchungModel;
    protected $ahAbrechnungModel;
    protected $hvAbrechnungModel;
    protected $schuldModel;

    public function __construct()
    {
        $this->belegModel = new BelegModel();
        $this->buchungModel = new BuchungModel();
        $this->ahAbrechnungModel = new AhAbrechnungModel();
        $this->hvAbrechnungModel = new HvAbrechnungModel();
        $this->schuldModel = new SchuldModel();
    }

    /**
     * Dashboard Hauptseite
     */
    public function index()
    {
        $kontostaende = $this->buchungModel->berechneKontostaende();
        $inventur = $this->schuldModel->berechneInventur();

        $data = [
            'title' => 'Dashboard - VDSt Kassensystem',

            'beleg_stats' => $this->belegModel->getDashboardStats(),
            'buchung_stats' => $this->buchungModel->getDashboardStats(),
            'ah_stats' => $this->ahAbrechnungModel->getDashboardStats(),
            'hv_stats' => $this->hvAbrechnungModel->getDashboardStats(),

            'kontostaende' => $kontostaende,
            'schulden_offen' => $this->schuldModel->summeOffeneForderungen(),
            'inventur_gesamt' => array_sum(array_column($kontostaende, 'saldo'))
                + ($inventur['forderung']['summe'] ?? 0)
                - ($inventur['verbindlichkeit']['summe'] ?? 0),
            'neueste_belege' => $this->belegModel->getNeuesteBelege(5),
            'neueste_buchungen' => $this->buchungModel->getNeuesteBuchungen(5),
        ];

        return view('dashboard/index', $data);
    }
}
