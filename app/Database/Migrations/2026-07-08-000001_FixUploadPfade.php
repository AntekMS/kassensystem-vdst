<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Korrigiert den historischen Upload-Pfad-Bug:
 *
 * generiereDateipfad() lieferte früher "public/uploads/…"; da die Controller
 * FCPATH (= public/) voranstellen, landeten Dateien in public/public/uploads/…
 * Diese Migration verschiebt bestehende Dateien nach public/uploads/… und
 * entfernt das "public/"-Präfix aus belege.dateipfad.
 */
class FixUploadPfade extends Migration
{
    public function up()
    {
        // 1. Dateien aus dem falsch verschachtelten Verzeichnis verschieben
        $falschesVerzeichnis = FCPATH . 'public/uploads';
        $korrektesVerzeichnis = FCPATH . 'uploads';

        if (is_dir($falschesVerzeichnis)) {
            $this->verschiebeRekursiv($falschesVerzeichnis, $korrektesVerzeichnis);
            $this->loescheLeereVerzeichnisse(FCPATH . 'public');
        }

        // 2. DB-Pfade korrigieren: "public/uploads/…" → "uploads/…"
        $this->db->query("
            UPDATE belege
            SET dateipfad = SUBSTRING(dateipfad, 8)
            WHERE dateipfad LIKE 'public/%'
        ");
    }

    public function down()
    {
        // Dateien bleiben am korrekten Ort; nur die DB-Pfade zurücksetzen
        $this->db->query("
            UPDATE belege
            SET dateipfad = CONCAT('public/', dateipfad)
            WHERE dateipfad LIKE 'uploads/%'
        ");
    }

    /**
     * Verschiebt alle Dateien rekursiv, bestehende Zieldateien bleiben erhalten.
     */
    private function verschiebeRekursiv(string $quelle, string $ziel): void
    {
        $eintraege = scandir($quelle) ?: [];

        foreach ($eintraege as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }

            $quellPfad = $quelle . DIRECTORY_SEPARATOR . $eintrag;
            $zielPfad = $ziel . DIRECTORY_SEPARATOR . $eintrag;

            if (is_dir($quellPfad)) {
                if (!is_dir($zielPfad)) {
                    mkdir($zielPfad, 0755, true);
                }
                $this->verschiebeRekursiv($quellPfad, $zielPfad);
            } elseif (!file_exists($zielPfad)) {
                if (!is_dir($ziel)) {
                    mkdir($ziel, 0755, true);
                }
                rename($quellPfad, $zielPfad);
            }
        }
    }

    /**
     * Räumt leere Verzeichnisse nach dem Verschieben auf.
     */
    private function loescheLeereVerzeichnisse(string $verzeichnis): void
    {
        if (!is_dir($verzeichnis)) {
            return;
        }

        foreach (scandir($verzeichnis) ?: [] as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }

            $pfad = $verzeichnis . DIRECTORY_SEPARATOR . $eintrag;
            if (is_dir($pfad)) {
                $this->loescheLeereVerzeichnisse($pfad);
            }
        }

        if (count(scandir($verzeichnis) ?: []) === 2) {
            rmdir($verzeichnis);
        }
    }
}
