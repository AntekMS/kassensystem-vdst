<?php

namespace App\Libraries;

use App\Models\BelegModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * BelegUpload - Gemeinsame Upload-Logik für Belege
 *
 * Wird vom BelegeController (direkter Upload) und vom
 * BuchungenController (Upload bei Buchungserstellung) verwendet:
 * Belegnummer generieren, Verzeichnis anlegen, Datei verschieben,
 * Datenbank-Eintrag erstellen, Aufräumen bei Fehlern.
 */
class BelegUpload
{
    protected BelegModel $belegModel;

    public function __construct(?BelegModel $belegModel = null)
    {
        $this->belegModel = $belegModel ?? new BelegModel();
    }

    /**
     * Speichert eine hochgeladene Beleg-Datei und legt den DB-Eintrag an.
     *
     * $daten braucht: rechnungsdatum, beschreibung, betrag.
     * Optional: lieferant, kategorie (Default 'normal'), notizen.
     *
     * Reihenfolge ist wichtig: Der DB-Eintrag wird ZUERST angelegt und die
     * Datei erst DANACH verschoben. So kann eine parallel vergebene Belegnummer
     * am Unique-Index scheitern (→ Retry mit neuer Nummer), ohne dass zuvor eine
     * Datei verschoben/überschrieben wurde. Schlägt das Verschieben fehl, wird
     * der eben angelegte Eintrag wieder entfernt.
     *
     * @return int Beleg-ID
     * @throws \RuntimeException bei Upload- oder Datenbank-Fehlern
     */
    public function speichereBeleg(UploadedFile $file, array $daten): int
    {
        // Datei-Informationen VOR dem Verschieben sammeln
        $originalName = $file->getName();
        $fileSize = $file->getSize();
        $extension = strtolower($file->getExtension());

        $rechnungsdatum = $daten['rechnungsdatum'];

        $belegId = null;
        $dateipfad = null;
        $systemDateiname = null;
        $vollstaendigerPfad = null;

        // Nummernvergabe + Insert mit Retry gegen parallele Uploads am selben Tag
        for ($versuch = 0; $versuch < 5 && !$belegId; $versuch++) {
            $belegnummer = $this->belegModel->generiereNaechsteBelegnummer($rechnungsdatum);

            if ($this->belegModel->belegnummerExistiert($belegnummer)) {
                continue;
            }

            $dateipfad = $this->belegModel->generiereDateipfad($rechnungsdatum);
            $systemDateiname = $this->belegModel->generiereSystemDateiname($belegnummer, $extension);
            $vollstaendigerPfad = $dateipfad . $systemDateiname;

            try {
                $belegId = $this->belegModel->insert([
                    'belegnummer' => $belegnummer,
                    'rechnungsdatum' => $rechnungsdatum,
                    'eingabedatum' => date('Y-m-d'),
                    'beschreibung' => $daten['beschreibung'],
                    'betrag' => $daten['betrag'],
                    'lieferant' => $daten['lieferant'] ?? null,
                    'dateiname_original' => $originalName,
                    'dateiname_system' => $systemDateiname,
                    'dateipfad' => $vollstaendigerPfad,
                    'dateityp' => $extension,
                    'dateigroesse' => $fileSize,
                    'kategorie' => $daten['kategorie'] ?? 'normal',
                    'status' => 'erfasst',
                    'notizen' => $daten['notizen'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // Vermutlich Unique-Konflikt durch parallelen Upload → neue Nummer versuchen
                $belegId = null;
            }
        }

        if (!$belegId) {
            $fehler = implode(' ', $this->belegModel->errors() ?: ['Fehler bei der Belegnummer-Generierung. Bitte versuchen Sie es erneut.']);
            throw new \RuntimeException($fehler);
        }

        // Datei erst nach erfolgreichem Insert ablegen
        try {
            self::erstelleVerzeichnis($dateipfad);
            $file->move(FCPATH . $dateipfad, $systemDateiname);
        } catch (\Throwable $e) {
            // Verschieben fehlgeschlagen → DB-Eintrag zurücknehmen, damit kein Beleg ohne Datei bleibt
            $this->belegModel->delete($belegId);
            throw new \RuntimeException('Fehler beim Speichern der Datei: ' . $e->getMessage());
        }

        return (int) $belegId;
    }

    /**
     * Legt ein Upload-Verzeichnis (relativ zu FCPATH) an, falls nötig.
     */
    public static function erstelleVerzeichnis(string $pfad): void
    {
        $vollstaendigerPfad = FCPATH . $pfad;

        if (!is_dir($vollstaendigerPfad)) {
            mkdir($vollstaendigerPfad, 0755, true);
        }
    }

    /**
     * Maximale Upload-Größe in MB aus den PHP-Limits.
     */
    public static function maxUploadSizeMb(): int
    {
        $limits = array_map(
            [self::class, 'parseSizeToMb'],
            [ini_get('upload_max_filesize'), ini_get('post_max_size')]
        );

        return (int) min($limits);
    }

    /**
     * Konvertiert php.ini-Größenangaben (z.B. "10M") zu MB.
     */
    protected static function parseSizeToMb(string $size): float
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = (float) preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            $size *= pow(1024, stripos('bkmgtpezy', $unit[0]));
        }

        return round($size / 1024 / 1024);
    }
}
