<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BelegModel;
use App\Models\BuchungModel;
use App\Models\AhAbrechnungModel;
use App\Models\HvAbrechnungModel;
use App\Models\AbrechnungBelegModel;

/**
 * DashboardController - Zentrale Übersicht
 *
 * Wie eine Kommandozentrale:
 * - Übersicht über alle wichtigen Kennzahlen
 * - Schnellzugriff auf häufige Aktionen
 * - Alerts für wichtige Aufgaben
 * - Quick-Upload für Belege
 */
class DashboardController extends BaseController
{
    protected $belegModel;
    protected $buchungModel;
    protected $ahAbrechnungModel;
    protected $hvAbrechnungModel;
    protected $abrechnungBelegModel;

    public function __construct()
    {
        $this->belegModel = new BelegModel();
        $this->buchungModel = new BuchungModel();
        $this->ahAbrechnungModel = new AhAbrechnungModel();
        $this->hvAbrechnungModel = new HvAbrechnungModel();
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
    }

    /**
     * Dashboard Hauptseite
     */
    public function index()
    {
        $data = [
            'title' => 'Dashboard - VDStE Kassensystem',

            // Statistiken sammeln
            'beleg_stats' => $this->belegModel->getDashboardStats(),
            'buchung_stats' => $this->buchungModel->getDashboardStats(),
            'ah_stats' => $this->ahAbrechnungModel->getDashboardStats(),
            'hv_stats' => $this->hvAbrechnungModel->getDashboardStats(),
            'zuordnung_stats' => $this->abrechnungBelegModel->getDashboardStats(),

            // Aktuelle Daten
            'kontostaende' => $this->buchungModel->berechneKontostaende(),
            'neueste_belege' => $this->belegModel->getNeuesteBelege(5),
            'neueste_buchungen' => $this->buchungModel->getNeuesteBuchungen(5),

            // Alerts und To-Dos
            'alerts' => $this->getSystemAlerts(),
            'quick_actions' => $this->getQuickActions(),

            // Monatsübersicht
            'aktueller_monat' => date('Y-m'),
            'monats_statistiken' => $this->getMonatsStatistiken(),

            // Upload-Informationen
            'max_upload_size' => $this->getMaxUploadSize()
        ];

        return view('dashboard/index', $data);
    }

    /**
     * Quick-Upload für Belege (AJAX)
     */
    public function quickUpload()
    {
        if (!$this->request->isAJAX()) {
            return redirect()->to('/dashboard');
        }

        $file = $this->request->getFile('quick_beleg');

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Keine gültige Datei erhalten'
            ]);
        }

        // Basis-Validierung
        $erlaubteTypen = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($file->getExtension()), $erlaubteTypen)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Nur PDF und Bilddateien sind erlaubt'
            ]);
        }

        try {
            // Temporärer Upload für spätere Bearbeitung
            $tempName = 'temp_' . uniqid() . '.' . $file->getExtension();
            $tempPath = FCPATH . 'uploads/temp/';

            if (!is_dir($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $file->move($tempPath, $tempName);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Datei hochgeladen! Bitte vervollständigen Sie die Angaben.',
                'temp_file' => $tempName,
                'original_name' => $file->getName(),
                'redirect_url' => base_url('/belege/create?temp=' . $tempName)
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Fehler beim Upload: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * System-Status für AJAX-Updates
     */
    public function getStatus()
    {
        if (!$this->request->isAJAX()) {
            return redirect()->to('/dashboard');
        }

        $data = [
            'neue_belege' => $this->belegModel->where('status', 'erfasst')->countAllResults(),
            'ausstehende_abrechnungen' => $this->getAusstehendeAbrechnungen(),
            'kontostand_gesamt' => $this->getGesamtkontostand(),
            'alerts_count' => count($this->getSystemAlerts())
        ];

        return $this->response->setJSON($data);
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * System-Alerts generieren
     */
    private function getSystemAlerts()
    {
        $alerts = [];

        // Neue Belege ohne Buchung
        $belegeOhneBuchung = $this->belegModel->select('COUNT(*) as anzahl')
            ->where('id NOT IN (SELECT COALESCE(beleg_id, 0) FROM buchungen WHERE beleg_id IS NOT NULL)')
            ->get()->getRow()->anzahl;

        if ($belegeOhneBuchung > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$belegeOhneBuchung} Belege haben noch keine Buchung",
                'link' => '/belege?filter=ohne_buchung',
                'icon' => 'exclamation-triangle'
            ];
        }

        // Ausstehende Abrechnungen
        $ausstehendeAH = $this->ahAbrechnungModel->where('status', 'ausstehend')->countAllResults();
        $ausstehendeHV = $this->hvAbrechnungModel->where('status', 'ausstehend')->countAllResults();

        if ($ausstehendeAH > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$ausstehendeAH} AH² Abrechnungen sind ausstehend",
                'link' => '/abrechnungen/ah',
                'icon' => 'clock'
            ];
        }

        if ($ausstehendeHV > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$ausstehendeHV} HV Abrechnungen sind ausstehend",
                'link' => '/abrechnungen/hv',
                'icon' => 'clock'
            ];
        }

        // Alte Entwürfe
        $alteEntwuerfe = $this->ahAbrechnungModel
            ->where('status', 'entwurf')
            ->where('created_at <', date('Y-m-d', strtotime('-30 days')))
            ->countAllResults();

        if ($alteEntwuerfe > 0) {
            $alerts[] = [
                'type' => 'secondary',
                'message' => "{$alteEntwuerfe} Abrechnungs-Entwürfe sind älter als 30 Tage",
                'link' => '/abrechnungen/ah',
                'icon' => 'archive'
            ];
        }

        return $alerts;
    }

    /**
     * Quick-Actions für häufige Aufgaben
     */
    private function getQuickActions()
    {
        return [
            [
                'title' => 'Neuen Beleg hinzufügen',
                'description' => 'PDF oder Bild hochladen',
                'link' => '/belege/create',
                'icon' => 'plus-circle',
                'color' => 'primary'
            ],
            [
                'title' => 'Neue Buchung',
                'description' => 'Kassenbuch-Eintrag erstellen',
                'link' => '/buchungen/create',
                'icon' => 'edit',
                'color' => 'success'
            ],
            [
                'title' => 'AH² Abrechnung',
                'description' => 'Neue Monatsabrechnung',
                'link' => '/abrechnungen/ah/create',
                'icon' => 'file-text',
                'color' => 'info'
            ],
            [
                'title' => 'HV Abrechnung',
                'description' => 'Hausausgaben abrechnen',
                'link' => '/abrechnungen/hv/create',
                'icon' => 'home',
                'color' => 'warning'
            ]
        ];
    }

    /**
     * Monatsstatistiken
     */
    private function getMonatsStatistiken()
    {
        $aktueller_monat = date('Y-m');

        return [
            'belege_diesen_monat' => $this->belegModel
                ->where('DATE_FORMAT(rechnungsdatum, "%Y-%m")', $aktueller_monat)
                ->countAllResults(),

            'buchungen_diesen_monat' => $this->buchungModel
                ->where('DATE_FORMAT(buchungsdatum, "%Y-%m")', $aktueller_monat)
                ->countAllResults(),

            'ausgaben_diesen_monat' => $this->buchungModel
                    ->where('DATE_FORMAT(buchungsdatum, "%Y-%m")', $aktueller_monat)
                    ->where('buchungsart', 'ausgabe')
                    ->selectSum('betrag')
                    ->get()->getRow()->betrag ?? 0,

            'einnahmen_diesen_monat' => $this->buchungModel
                    ->where('DATE_FORMAT(buchungsdatum, "%Y-%m")', $aktueller_monat)
                    ->where('buchungsart', 'einnahme')
                    ->selectSum('betrag')
                    ->get()->getRow()->betrag ?? 0
        ];
    }

    /**
     * Ausstehende Abrechnungen zählen
     */
    private function getAusstehendeAbrechnungen()
    {
        $ah = $this->ahAbrechnungModel->where('status', 'ausstehend')->countAllResults();
        $hv = $this->hvAbrechnungModel->where('status', 'ausstehend')->countAllResults();

        return $ah + $hv;
    }

    /**
     * Gesamtkontostand berechnen
     */
    private function getGesamtkontostand()
    {
        $kontostaende = $this->buchungModel->berechneKontostaende();
        $gesamt = 0;

        foreach ($kontostaende as $konto) {
            $gesamt += $konto['saldo'];
        }

        return $gesamt;
    }

    /**
     * Maximale Upload-Größe ermitteln
     */
    private function getMaxUploadSize()
    {
        $max_upload = ini_get('upload_max_filesize');
        $max_post = ini_get('post_max_size');

        $upload_mb = $this->parseSize($max_upload);
        $post_mb = $this->parseSize($max_post);

        return min($upload_mb, $post_mb);
    }

    /**
     * Größenangaben parsen
     */
    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])) / 1024 / 1024);
        } else {
            return round($size / 1024 / 1024);
        }
    }
}