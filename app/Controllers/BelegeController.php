<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\BelegModel;
use App\Models\AbrechnungBelegModel;
use App\Models\BuchungModel;
use CodeIgniter\Files\File;

/**
 * BelegeController - Kern-Controller für Beleg-Verwaltung
 *
 * Wie ein intelligenter Aktenverwalter:
 * - Upload und Verwaltung von Belegen (PDF, Bilder)
 * - Automatische Belegnummer-Generierung
 * - Datei-Umbenennung und Organisation
 * - Vorschau und Download von Belegen
 */
class BelegeController extends BaseController
{
    protected $belegModel;
    protected $abrechnungBelegModel;
    protected $buchungModel;

    public function __construct()
    {
        $this->belegModel = new BelegModel();
        $this->abrechnungBelegModel = new AbrechnungBelegModel();
        $this->buchungModel = new BuchungModel();
    }

    /**
     * Übersicht aller Belege
     * Wie ein Aktenordner mit Suchfunktion
     */
    public function index()
    {
        $filter = $this->getFilterFromRequest();

        // Belege basierend auf Filter holen
        $belege = $this->belegModel->sucheBelege($filter);

        // Für jeden Beleg prüfen ob er in Abrechnungen ist
        foreach ($belege as &$beleg) {
            $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($beleg['id']);
            $beleg['abrechnungen'] = $abrechnungen;
        }

        $data = [
            'title' => 'Belege-Übersicht',
            'belege' => $belege,
            'filter' => $filter,
            'stats' => $this->belegModel->getDashboardStats(),
            // Für Filter-Dropdowns
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ],
            'status_optionen' => [
                'erfasst' => 'Erfasst',
                'in_abrechnung' => 'In Abrechnung',
                'abgerechnet' => 'Abgerechnet',
                'bezahlt' => 'Bezahlt'
            ]
        ];

        return view('belege/index', $data);
    }

    /**
     * Formular für neuen Beleg
     * Wie ein Eingabe-Formular für neue Akten
     */
    public function create()
    {
        $data = [
            'title' => 'Neuen Beleg hinzufügen',
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ],
            'max_upload_size' => $this->getMaxUploadSize()
        ];

        return view('belege/create', $data);
    }

    /**
     * Speichert neuen Beleg mit Datei-Upload
     * Wie das Einheften eines neuen Dokuments
     */
    public function store()
    {
        $validation = \Config\Services::validation();

        // Validierungs-Regeln
        $rules = [
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]',
            'beleg_datei' => 'uploaded[beleg_datei]|max_size[beleg_datei,10240]|ext_in[beleg_datei,pdf,jpg,jpeg,png]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $file = $this->request->getFile('beleg_datei');

        if (!$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Datei-Upload: ' . $file->getErrorString());
        }

        // Belegnummer generieren
        $rechnungsdatum = $this->request->getPost('rechnungsdatum');
        $belegnummer = $this->belegModel->generiereNaechsteBelegnummer($rechnungsdatum);

        // Prüfe ob Belegnummer bereits existiert (Sicherheitscheck)
        if ($this->belegModel->belegnummerExistiert($belegnummer)) {
            return redirect()->back()->withInput()->with('error', 'Fehler bei der Belegnummer-Generierung. Bitte versuchen Sie es erneut.');
        }

        try {
            // Dateipfad und Namen generieren
            $dateipfad = $this->belegModel->generiereDateipfad($rechnungsdatum);
            $systemDateiname = $this->belegModel->generiereSystemDateiname($belegnummer, $file->getExtension());
            $vollstaendigerPfad = $dateipfad . $systemDateiname;

            // Verzeichnis erstellen falls nicht vorhanden
            $this->erstelleVerzeichnisStruktur($dateipfad);

            // Datei verschieben und umbenennen
            $file->move(FCPATH . $dateipfad, $systemDateiname);

            // Beleg-Daten für Datenbank vorbereiten
            $belegData = [
                'belegnummer' => $belegnummer,
                'rechnungsdatum' => $rechnungsdatum,
                'eingabedatum' => date('Y-m-d'),
                'beschreibung' => $this->request->getPost('beschreibung'),
                'betrag' => $this->request->getPost('betrag'),
                'lieferant' => $this->request->getPost('lieferant') ?: null,
                'dateiname_original' => $file->getName(),
                'dateiname_system' => $systemDateiname,
                'dateipfad' => $vollstaendigerPfad,
                'dateityp' => strtolower($file->getExtension()),
                'dateigroesse' => $file->getSize(),
                'kategorie' => $this->request->getPost('kategorie'),
                'status' => 'erfasst',
                'notizen' => $this->request->getPost('notizen') ?: null
            ];

            // In Datenbank speichern
            $belegId = $this->belegModel->insert($belegData);

            if ($belegId) {
                $message = "Beleg {$belegnummer} wurde erfolgreich erstellt!";
                return redirect()->to('/belege')->with('success', $message);
            } else {
                // Datei wieder löschen bei DB-Fehler
                unlink(FCPATH . $vollstaendigerPfad);
                return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern in der Datenbank.');
            }

        } catch (\Exception $e) {
            // Aufräumen bei Fehlern
            if (isset($vollstaendigerPfad) && file_exists(FCPATH . $vollstaendigerPfad)) {
                unlink(FCPATH . $vollstaendigerPfad);
            }

            log_message('error', 'Beleg-Upload Fehler: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Ein unerwarteter Fehler ist aufgetreten.');
        }
    }

    /**
     * Zeigt einen einzelnen Beleg mit Datei-Vorschau
     * Wie das Öffnen einer Akte zur Ansicht
     */
    public function show($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        // Abrechnungen für diesen Beleg holen
        $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id);

        $data = [
            'title' => 'Beleg ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'abrechnungen' => $abrechnungen,
            'kann_bearbeitet_werden' => $beleg['status'] === 'erfasst',
            'datei_existiert' => file_exists(FCPATH . $beleg['dateipfad'])
        ];

        return view('belege/show', $data);
    }

    /**
     * Bearbeitung eines Belegs
     * Wie das Überarbeiten einer Akte
     */
    public function edit($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        // Prüfe ob Beleg bearbeitet werden kann
        if ($beleg['status'] !== 'erfasst') {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht bearbeitet werden (Status: ' . $beleg['status'] . ')');
        }

        $data = [
            'title' => 'Beleg bearbeiten: ' . $beleg['belegnummer'],
            'beleg' => $beleg,
            'kategorien' => [
                'normal' => 'Normal',
                'ah_berechtigt' => 'AH² berechtigt',
                'hv_berechtigt' => 'HV berechtigt'
            ]
        ];

        return view('belege/edit', $data);
    }

    /**
     * Aktualisiert einen Beleg
     * Wie das Speichern von Änderungen an einer Akte
     */
    public function update($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg || $beleg['status'] !== 'erfasst') {
            return redirect()->to('/belege')->with('error', 'Beleg kann nicht bearbeitet werden.');
        }

        $validation = \Config\Services::validation();

        $rules = [
            'rechnungsdatum' => 'required|valid_date',
            'beschreibung' => 'required|min_length[3]|max_length[500]',
            'betrag' => 'required|decimal|greater_than[0]',
            'kategorie' => 'required|in_list[normal,ah_berechtigt,hv_berechtigt]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        // Prüfe ob sich das Rechnungsdatum geändert hat
        $neuesRechnungsdatum = $this->request->getPost('rechnungsdatum');
        $datumGeaendert = $beleg['rechnungsdatum'] !== $neuesRechnungsdatum;

        $updateData = [
            'rechnungsdatum' => $neuesRechnungsdatum,
            'beschreibung' => $this->request->getPost('beschreibung'),
            'betrag' => $this->request->getPost('betrag'),
            'lieferant' => $this->request->getPost('lieferant') ?: null,
            'kategorie' => $this->request->getPost('kategorie'),
            'notizen' => $this->request->getPost('notizen') ?: null
        ];

        // Wenn Datum geändert wurde, neue Belegnummer generieren und Datei verschieben
        if ($datumGeaendert) {
            $neue_belegnummer = $this->belegModel->generiereNaechsteBelegnummer($neuesRechnungsdatum);

            if ($this->belegModel->belegnummerExistiert($neue_belegnummer, $id)) {
                return redirect()->back()->withInput()->with('error', 'Belegnummer für das neue Datum ist bereits vergeben.');
            }

            // Datei umbenennen und verschieben
            if ($this->verschiebeBeleg($beleg, $neue_belegnummer, $neuesRechnungsdatum)) {
                $updateData['belegnummer'] = $neue_belegnummer;
                $updateData['dateiname_system'] = $neue_belegnummer . '.' . $beleg['dateityp'];
                $updateData['dateipfad'] = $this->belegModel->generiereDateipfad($neuesRechnungsdatum) . $updateData['dateiname_system'];
            } else {
                return redirect()->back()->withInput()->with('error', 'Fehler beim Verschieben der Datei.');
            }
        }

        if ($this->belegModel->update($id, $updateData)) {
            $belegnummer = $datumGeaendert ? $updateData['belegnummer'] : $beleg['belegnummer'];
            return redirect()->to("/belege/show/{$id}")
                ->with('success', "Beleg {$belegnummer} wurde erfolgreich aktualisiert!");
        } else {
            return redirect()->back()->withInput()->with('error', 'Fehler beim Speichern der Änderungen.');
        }
    }

    /**
     * Löscht einen Beleg (nur wenn noch nicht in Abrechnungen)
     * Wie das Vernichten einer Akte
     */
    public function delete($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            return redirect()->to('/belege')->with('error', 'Beleg nicht gefunden.');
        }

        // Prüfe ob Beleg in Abrechnungen verwendet wird
        $abrechnungen = $this->abrechnungBelegModel->getAbrechnungenFuerBeleg($id);

        if (!empty($abrechnungen)) {
            $abrechnungsListe = [];
            foreach ($abrechnungen as $abrechnung) {
                $abrechnungsListe[] = $abrechnung['titel'] . ' (' . strtoupper($abrechnung['typ']) . ')';
            }
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er in folgenden Abrechnungen verwendet wird: ' . implode(', ', $abrechnungsListe));
        }

        // Prüfe ob Beleg in Buchungen verwendet wird - KORRIGIERT
        $buchungen = $this->buchungModel->where('beleg_id', $id)->findAll();
        if (!empty($buchungen)) {
            return redirect()->to("/belege/show/{$id}")
                ->with('error', 'Beleg kann nicht gelöscht werden, da er mit einer Buchung verknüpft ist. Löschen Sie zuerst die Buchung.');
        }

        try {
            // Datei löschen
            $dateipfad = FCPATH . $beleg['dateipfad'];
            if (file_exists($dateipfad)) {
                if (!unlink($dateipfad)) {
                    log_message('warning', 'Datei konnte nicht gelöscht werden: ' . $dateipfad);
                }
            }

            // Aus Datenbank löschen
            if ($this->belegModel->delete($id)) {
                return redirect()->to('/belege')
                    ->with('success', "Beleg {$beleg['belegnummer']} und die zugehörige Datei wurden erfolgreich gelöscht!");
            } else {
                return redirect()->to('/belege')->with('error', 'Fehler beim Löschen des Belegs aus der Datenbank.');
            }

        } catch (\Exception $e) {
            log_message('error', 'Beleg-Löschung Fehler: ' . $e->getMessage());
            return redirect()->to('/belege')->with('error', 'Ein Fehler ist beim Löschen aufgetreten: ' . $e->getMessage());
        }
    }

    /**
     * Download der Original-Datei
     * Wie das Herausnehmen einer Kopie aus der Akte
     */
    public function download($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Beleg nicht gefunden');
        }

        $dateipfad = FCPATH . $beleg['dateipfad'];

        if (!file_exists($dateipfad)) {
            return redirect()->back()->with('error', 'Datei nicht gefunden.');
        }

        // Download mit originalem Namen
        return $this->response->download($dateipfad, null, true)
            ->setFileName($beleg['dateiname_original']);
    }

    /**
     * AJAX-Endpoint für Datei-Vorschau
     */
    public function preview($id)
    {
        $beleg = $this->belegModel->find($id);

        if (!$beleg) {
            return $this->response->setJSON(['error' => 'Beleg nicht gefunden']);
        }

        $dateipfad = FCPATH . $beleg['dateipfad'];

        if (!file_exists($dateipfad)) {
            return $this->response->setJSON(['error' => 'Datei nicht gefunden']);
        }

        // Für PDFs und Bilder unterschiedlich behandeln
        if ($beleg['dateityp'] === 'pdf') {
            return $this->response->setHeader('Content-Type', 'application/pdf')
                ->setBody(file_get_contents($dateipfad));
        } else {
            $mimeType = mime_content_type($dateipfad);
            return $this->response->setHeader('Content-Type', $mimeType)
                ->setBody(file_get_contents($dateipfad));
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Extrahiert Filter aus Request
     */
    private function getFilterFromRequest()
    {
        return [
            'suche' => $this->request->getGet('suche'),
            'kategorie' => $this->request->getGet('kategorie'),
            'status' => $this->request->getGet('status'),
            'datum_von' => $this->request->getGet('datum_von'),
            'datum_bis' => $this->request->getGet('datum_bis'),
            'betrag_min' => $this->request->getGet('betrag_min'),
            'betrag_max' => $this->request->getGet('betrag_max')
        ];
    }

    /**
     * Erstellt Verzeichnisstruktur
     */
    private function erstelleVerzeichnisStruktur($pfad)
    {
        $vollstaendigerPfad = FCPATH . $pfad;

        if (!is_dir($vollstaendigerPfad)) {
            mkdir($vollstaendigerPfad, 0755, true);
        }
    }

    /**
     * Verschiebt Beleg-Datei bei Datum-Änderung
     */
    private function verschiebeBeleg($beleg, $neue_belegnummer, $neues_datum)
    {
        $alter_pfad = FCPATH . $beleg['dateipfad'];
        $neuer_pfad = FCPATH . $this->belegModel->generiereDateipfad($neues_datum);
        $neuer_dateiname = $neue_belegnummer . '.' . $beleg['dateityp'];
        $neuer_vollpfad = $neuer_pfad . $neuer_dateiname;

        try {
            $this->erstelleVerzeichnisStruktur($this->belegModel->generiereDateipfad($neues_datum));
            return rename($alter_pfad, $neuer_vollpfad);
        } catch (\Exception $e) {
            log_message('error', 'Beleg-Verschiebung Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Holt maximale Upload-Größe
     */
    private function getMaxUploadSize()
    {
        $max_upload = ini_get('upload_max_filesize');
        $max_post = ini_get('post_max_size');
        $memory_limit = ini_get('memory_limit');

        $upload_mb = $this->parseSize($max_upload);
        $post_mb = $this->parseSize($max_post);
        $memory_mb = $this->parseSize($memory_limit);

        return min($upload_mb, $post_mb, $memory_mb);
    }

    /**
     * Konvertiert Größenangaben zu MB
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