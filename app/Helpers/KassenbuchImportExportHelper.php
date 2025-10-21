<?php

namespace App\Helpers;

use CodeIgniter\Database\BaseConnection;

/**
 * KassenbuchImportExportHelper - Komplettes Backup-System
 *
 * Wie ein digitaler Tresor mit Umzugsfunktion:
 * - Export: Komplettes Kassenbuch + alle Belege + Abrechnungen als portables Archiv
 * - Import: Zusammenführen oder Überschreiben mit Konflikt-Erkennung
 * - Format: JSON + Dateien (menschenlesbar und effizient)
 */
class KassenbuchImportExportHelper
{
    /**
     * Erstellt komplettes Backup-Archiv
     *
     * @return string|false Pfad zur ZIP-Datei oder false bei Fehler
     */
    public static function erstelleKomplettBackup()
    {
        try {
            $db = \Config\Database::connect();

            // Temporäres Verzeichnis
            $tempDir = WRITEPATH . 'temp/backup/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backupDir = $tempDir . 'backup_' . $timestamp . '/';
            mkdir($backupDir, 0755, true);

            // 1. EXPORT BUCHUNGEN
            $buchungen = $db->table('buchungen')
                ->select('*')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            file_put_contents(
                $backupDir . 'buchungen.json',
                json_encode($buchungen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 2. EXPORT BELEGE (Metadaten)
            $belege = $db->table('belege')
                ->select('*')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            file_put_contents(
                $backupDir . 'belege.json',
                json_encode($belege, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 3. EXPORT ABRECHNUNGEN
            $ahAbrechnungen = $db->table('ah_abrechnungen')
                ->select('*')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $hvAbrechnungen = $db->table('hv_abrechnungen')
                ->select('*')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            file_put_contents(
                $backupDir . 'ah_abrechnungen.json',
                json_encode($ahAbrechnungen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            file_put_contents(
                $backupDir . 'hv_abrechnungen.json',
                json_encode($hvAbrechnungen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 4. EXPORT ABRECHNUNGS-VERKNÜPFUNGEN
            $abrechnungBelege = $db->table('abrechnung_belege')
                ->select('*')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            file_put_contents(
                $backupDir . 'abrechnung_belege.json',
                json_encode($abrechnungBelege, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 5. EXPORT SYSTEM-EINSTELLUNGEN
            $einstellungen = $db->table('system_einstellungen')
                ->select('*')
                ->get()
                ->getResultArray();

            file_put_contents(
                $backupDir . 'system_einstellungen.json',
                json_encode($einstellungen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 6. BELEG-DATEIEN KOPIEREN
            $belegeDateienDir = $backupDir . 'beleg_dateien/';
            mkdir($belegeDateienDir, 0755, true);

            $kopierteBeleg = 0;
            $fehlendeBeleg = 0;
            $fehlendeBeleg_liste = [];

            foreach ($belege as $beleg) {
                if (empty($beleg['dateipfad'])) {
                    continue;
                }

                $quellDatei = FCPATH . $beleg['dateipfad'];

                if (file_exists($quellDatei)) {
                    // Sichere Pfadstruktur beibehalten
                    $relativerPfad = str_replace('public/', '', $beleg['dateipfad']);
                    $zielPfad = $belegeDateienDir . $relativerPfad;

                    // Verzeichnis erstellen
                    $zielDir = dirname($zielPfad);
                    if (!is_dir($zielDir)) {
                        mkdir($zielDir, 0755, true);
                    }

                    copy($quellDatei, $zielPfad);
                    $kopierteBeleg++;
                } else {
                    $fehlendeBeleg++;
                    $fehlendeBeleg_liste[] = $beleg['belegnummer'];
                }
            }

            // 7. BACKUP-METADATEN ERSTELLEN
            $metadaten = [
                'backup_erstellt_am' => date('Y-m-d H:i:s'),
                'kassensystem_version' => defined('VDST_VERSION') ? VDST_VERSION : '1.0.x',
                'php_version' => PHP_VERSION,
                'statistiken' => [
                    'buchungen_anzahl' => count($buchungen),
                    'belege_anzahl' => count($belege),
                    'belege_dateien_kopiert' => $kopierteBeleg,
                    'belege_dateien_fehlend' => $fehlendeBeleg,
                    'ah_abrechnungen' => count($ahAbrechnungen),
                    'hv_abrechnungen' => count($hvAbrechnungen),
                    'abrechnung_verknuepfungen' => count($abrechnungBelege)
                ],
                'fehlende_dateien' => $fehlendeBeleg_liste,
                'kontostaende_beim_export' => self::berechneKontostaende($db),
                'export_umfang' => 'komplett',
                'hinweis' => 'Dieses Backup enthält alle Buchungen, Belege, Abrechnungen und Verknüpfungen'
            ];

            file_put_contents(
                $backupDir . 'backup_info.json',
                json_encode($metadaten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // 8. README ERSTELLEN
            $readme = self::erstelleBackupReadme($metadaten);
            file_put_contents($backupDir . 'README.txt', $readme);

            // 9. ZIP ERSTELLEN
            $zipFilename = 'kassenbuch_komplett_backup_' . $timestamp . '.zip';
            $zipPath = $tempDir . $zipFilename;

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht erstellt werden');
            }

            // Alle Dateien rekursiv zur ZIP hinzufügen
            self::addDirectoryToZip($zip, $backupDir, '');

            $zip->close();

            // Temporäres Verzeichnis aufräumen
            self::deleteDirectory($backupDir);

            return $zipPath;

        } catch (\Exception $e) {
            log_message('error', 'Backup-Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Analysiert Import-Archiv und gibt Vorschau zurück
     *
     * @param string $zipPath Pfad zur ZIP-Datei
     * @return array|false Vorschau-Daten oder false bei Fehler
     */
    public static function analysiereImportArchiv($zipPath)
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception('ZIP-Archiv konnte nicht geöffnet werden');
            }

            // Temporäres Verzeichnis zum Entpacken
            $tempDir = WRITEPATH . 'temp/import_preview/';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $extractDir = $tempDir . uniqid('preview_') . '/';
            mkdir($extractDir, 0755, true);

            $zip->extractTo($extractDir);
            $zip->close();

            // Backup-Info laden
            $infoFile = $extractDir . 'backup_info.json';
            if (!file_exists($infoFile)) {
                throw new \Exception('Keine gültige Backup-Datei (backup_info.json fehlt)');
            }

            $backupInfo = json_decode(file_get_contents($infoFile), true);

            // Daten laden
            $buchungen = json_decode(file_get_contents($extractDir . 'buchungen.json'), true);
            $belege = json_decode(file_get_contents($extractDir . 'belege.json'), true);
            $ahAbrechnungen = json_decode(file_get_contents($extractDir . 'ah_abrechnungen.json'), true);
            $hvAbrechnungen = json_decode(file_get_contents($extractDir . 'hv_abrechnungen.json'), true);

            // Konflikte analysieren
            $db = \Config\Database::connect();

            $konflikteAnalyse = [
                'buchungen' => self::analysiereBuchungenKonflikte($db, $buchungen),
                'belege' => self::analysiereBelegeKonflikte($db, $belege),
                'ah_abrechnungen' => self::analysiereAbrechnungenKonflikte($db, $ahAbrechnungen, 'ah_abrechnungen'),
                'hv_abrechnungen' => self::analysiereAbrechnungenKonflikte($db, $hvAbrechnungen, 'hv_abrechnungen')
            ];

            $vorschau = [
                'backup_info' => $backupInfo,
                'import_statistik' => [
                    'buchungen_gesamt' => count($buchungen),
                    'belege_gesamt' => count($belege),
                    'ah_abrechnungen_gesamt' => count($ahAbrechnungen),
                    'hv_abrechnungen_gesamt' => count($hvAbrechnungen)
                ],
                'konflikte' => $konflikteAnalyse,
                'empfehlung' => self::generiereImportEmpfehlung($konflikteAnalyse),
                'extract_dir' => $extractDir // Für späteren Import
            ];

            return $vorschau;

        } catch (\Exception $e) {
            log_message('error', 'Import-Analyse-Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Führt Import durch
     *
     * @param string $extractDir Entpacktes Verzeichnis
     * @param array $optionen Import-Optionen
     * @return array Ergebnis mit Statistiken
     */
    public static function importiereKassenbuch($extractDir, $optionen = [])
    {
        $db = \Config\Database::connect();

        // Standard-Optionen
        $optionen = array_merge([
            'modus' => 'merge', // 'merge' oder 'overwrite'
            'belegnummern_beibehalten' => true,
            'datumsstempel_original' => true,
            'beleg_dateien_kopieren' => true
        ], $optionen);

        $protokoll = [
            'start' => date('Y-m-d H:i:s'),
            'buchungen' => ['neu' => 0, 'aktualisiert' => 0, 'fehler' => 0],
            'belege' => ['neu' => 0, 'aktualisiert' => 0, 'fehler' => 0],
            'beleg_dateien' => ['kopiert' => 0, 'fehlend' => 0],
            'ah_abrechnungen' => ['neu' => 0, 'aktualisiert' => 0],
            'hv_abrechnungen' => ['neu' => 0, 'aktualisiert' => 0],
            'fehler' => []
        ];

        try {
            $db->transStart();

            // 1. BELEGE IMPORTIEREN (zuerst, wegen Foreign Keys)
            $belege = json_decode(file_get_contents($extractDir . 'belege.json'), true);
            $belegIdMapping = []; // Alt-ID => Neu-ID

            foreach ($belege as $beleg) {
                $altId = $beleg['id'];
                unset($beleg['id']); // Lasse DB neue ID generieren

                // Prüfe ob Belegnummer existiert
                $existierend = $db->table('belege')
                    ->where('belegnummer', $beleg['belegnummer'])
                    ->get()
                    ->getRowArray();

                if ($existierend) {
                    if ($optionen['modus'] === 'overwrite') {
                        // Aktualisieren
                        $db->table('belege')
                            ->where('id', $existierend['id'])
                            ->update($beleg);
                        $belegIdMapping[$altId] = $existierend['id'];
                        $protokoll['belege']['aktualisiert']++;
                    } else {
                        // Verwende existierende ID
                        $belegIdMapping[$altId] = $existierend['id'];
                    }
                } else {
                    // Neu einfügen
                    $db->table('belege')->insert($beleg);
                    $belegIdMapping[$altId] = $db->insertID();
                    $protokoll['belege']['neu']++;
                }
            }

            // 2. BELEG-DATEIEN KOPIEREN
            if ($optionen['beleg_dateien_kopieren']) {
                $belegeDateienDir = $extractDir . 'beleg_dateien/';

                if (is_dir($belegeDateienDir)) {
                    foreach ($belege as $beleg) {
                        if (empty($beleg['dateipfad'])) continue;

                        $relativerPfad = str_replace('public/', '', $beleg['dateipfad']);
                        $quellDatei = $belegeDateienDir . $relativerPfad;
                        $zielDatei = FCPATH . $beleg['dateipfad'];

                        if (file_exists($quellDatei)) {
                            // Verzeichnis erstellen falls nötig
                            $zielDir = dirname($zielDatei);
                            if (!is_dir($zielDir)) {
                                mkdir($zielDir, 0755, true);
                            }

                            copy($quellDatei, $zielDatei);
                            $protokoll['beleg_dateien']['kopiert']++;
                        } else {
                            $protokoll['beleg_dateien']['fehlend']++;
                        }
                    }
                }
            }

            // 3. BUCHUNGEN IMPORTIEREN
            $buchungen = json_decode(file_get_contents($extractDir . 'buchungen.json'), true);

            foreach ($buchungen as $buchung) {
                unset($buchung['id']);

                // Beleg-ID anpassen falls vorhanden
                if (!empty($buchung['beleg_id']) && isset($belegIdMapping[$buchung['beleg_id']])) {
                    $buchung['beleg_id'] = $belegIdMapping[$buchung['beleg_id']];
                }

                // Prüfe auf Duplikat (gleiche Beschreibung, Datum, Betrag)
                $existierend = $db->table('buchungen')
                    ->where('buchungsdatum', $buchung['buchungsdatum'])
                    ->where('beschreibung', $buchung['beschreibung'])
                    ->where('betrag', $buchung['betrag'])
                    ->where('konto_typ', $buchung['konto_typ'])
                    ->get()
                    ->getRowArray();

                if ($existierend) {
                    if ($optionen['modus'] === 'overwrite') {
                        $db->table('buchungen')
                            ->where('id', $existierend['id'])
                            ->update($buchung);
                        $protokoll['buchungen']['aktualisiert']++;
                    }
                    // Bei 'merge' überspringen wir Duplikate
                } else {
                    $db->table('buchungen')->insert($buchung);
                    $protokoll['buchungen']['neu']++;
                }
            }

            // 4. AH-ABRECHNUNGEN IMPORTIEREN
            $ahAbrechnungen = json_decode(file_get_contents($extractDir . 'ah_abrechnungen.json'), true);
            $ahIdMapping = [];

            foreach ($ahAbrechnungen as $abrechnung) {
                $altId = $abrechnung['id'];
                unset($abrechnung['id']);

                $existierend = $db->table('ah_abrechnungen')
                    ->where('abrechnungsmonat', $abrechnung['abrechnungsmonat'])
                    ->get()
                    ->getRowArray();

                if ($existierend) {
                    if ($optionen['modus'] === 'overwrite') {
                        $db->table('ah_abrechnungen')
                            ->where('id', $existierend['id'])
                            ->update($abrechnung);
                        $ahIdMapping[$altId] = $existierend['id'];
                        $protokoll['ah_abrechnungen']['aktualisiert']++;
                    } else {
                        $ahIdMapping[$altId] = $existierend['id'];
                    }
                } else {
                    $db->table('ah_abrechnungen')->insert($abrechnung);
                    $ahIdMapping[$altId] = $db->insertID();
                    $protokoll['ah_abrechnungen']['neu']++;
                }
            }

            // 5. HV-ABRECHNUNGEN IMPORTIEREN
            $hvAbrechnungen = json_decode(file_get_contents($extractDir . 'hv_abrechnungen.json'), true);
            $hvIdMapping = [];

            foreach ($hvAbrechnungen as $abrechnung) {
                $altId = $abrechnung['id'];
                unset($abrechnung['id']);

                $existierend = $db->table('hv_abrechnungen')
                    ->where('abrechnungsmonat', $abrechnung['abrechnungsmonat'])
                    ->get()
                    ->getRowArray();

                if ($existierend) {
                    if ($optionen['modus'] === 'overwrite') {
                        $db->table('hv_abrechnungen')
                            ->where('id', $existierend['id'])
                            ->update($abrechnung);
                        $hvIdMapping[$altId] = $existierend['id'];
                        $protokoll['hv_abrechnungen']['aktualisiert']++;
                    } else {
                        $hvIdMapping[$altId] = $existierend['id'];
                    }
                } else {
                    $db->table('hv_abrechnungen')->insert($abrechnung);
                    $hvIdMapping[$altId] = $db->insertID();
                    $protokoll['hv_abrechnungen']['neu']++;
                }
            }

            // 6. ABRECHNUNGS-VERKNÜPFUNGEN IMPORTIEREN
            $abrechnungBelege = json_decode(file_get_contents($extractDir . 'abrechnung_belege.json'), true);

            foreach ($abrechnungBelege as $verknuepfung) {
                // IDs anpassen
                if ($verknuepfung['abrechnung_typ'] === 'ah' && isset($ahIdMapping[$verknuepfung['abrechnung_id']])) {
                    $verknuepfung['abrechnung_id'] = $ahIdMapping[$verknuepfung['abrechnung_id']];
                } elseif ($verknuepfung['abrechnung_typ'] === 'hv' && isset($hvIdMapping[$verknuepfung['abrechnung_id']])) {
                    $verknuepfung['abrechnung_id'] = $hvIdMapping[$verknuepfung['abrechnung_id']];
                }

                if (isset($belegIdMapping[$verknuepfung['beleg_id']])) {
                    $verknuepfung['beleg_id'] = $belegIdMapping[$verknuepfung['beleg_id']];
                }

                // Prüfe ob Verknüpfung existiert
                $existierend = $db->table('abrechnung_belege')
                    ->where('abrechnung_typ', $verknuepfung['abrechnung_typ'])
                    ->where('abrechnung_id', $verknuepfung['abrechnung_id'])
                    ->where('beleg_id', $verknuepfung['beleg_id'])
                    ->get()
                    ->getRowArray();

                if (!$existierend) {
                    unset($verknuepfung['id']);
                    $db->table('abrechnung_belege')->insert($verknuepfung);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaktion fehlgeschlagen');
            }

            $protokoll['ende'] = date('Y-m-d H:i:s');
            $protokoll['erfolg'] = true;

            // Temporäres Verzeichnis aufräumen
            self::deleteDirectory($extractDir);

            return $protokoll;

        } catch (\Exception $e) {
            $db->transRollback();
            $protokoll['erfolg'] = false;
            $protokoll['fehler'][] = $e->getMessage();
            log_message('error', 'Import-Fehler: ' . $e->getMessage());
            return $protokoll;
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private static function berechneKontostaende($db)
    {
        $konten = ['aktivenkasse', 'getraenkekasse', 'barkasse'];
        $staende = [];

        foreach ($konten as $konto) {
            $einnahmen = $db->table('buchungen')
                ->where('konto_typ', $konto)
                ->where('buchungsart', 'einnahme')
                ->selectSum('betrag')
                ->get()
                ->getRow()
                ->betrag ?? 0;

            $ausgaben = $db->table('buchungen')
                ->where('konto_typ', $konto)
                ->where('buchungsart', 'ausgabe')
                ->selectSum('betrag')
                ->get()
                ->getRow()
                ->betrag ?? 0;

            $staende[$konto] = [
                'einnahmen' => $einnahmen,
                'ausgaben' => $ausgaben,
                'saldo' => $einnahmen - $ausgaben
            ];
        }

        return $staende;
    }

    private static function analysiereBuchungenKonflikte($db, $buchungen)
    {
        $konflikte = [
            'duplikate' => 0,
            'neue' => 0,
            'beispiele' => []
        ];

        foreach ($buchungen as $buchung) {
            $existierend = $db->table('buchungen')
                ->where('buchungsdatum', $buchung['buchungsdatum'])
                ->where('beschreibung', $buchung['beschreibung'])
                ->where('betrag', $buchung['betrag'])
                ->get()
                ->getRowArray();

            if ($existierend) {
                $konflikte['duplikate']++;
                if (count($konflikte['beispiele']) < 5) {
                    $konflikte['beispiele'][] = [
                        'datum' => $buchung['buchungsdatum'],
                        'beschreibung' => substr($buchung['beschreibung'], 0, 40),
                        'betrag' => $buchung['betrag']
                    ];
                }
            } else {
                $konflikte['neue']++;
            }
        }

        return $konflikte;
    }

    private static function analysiereBelegeKonflikte($db, $belege)
    {
        $konflikte = [
            'duplikate_belegnummer' => 0,
            'neue' => 0,
            'beispiele' => []
        ];

        foreach ($belege as $beleg) {
            $existierend = $db->table('belege')
                ->where('belegnummer', $beleg['belegnummer'])
                ->get()
                ->getRowArray();

            if ($existierend) {
                $konflikte['duplikate_belegnummer']++;
                if (count($konflikte['beispiele']) < 5) {
                    $konflikte['beispiele'][] = [
                        'belegnummer' => $beleg['belegnummer'],
                        'beschreibung' => substr($beleg['beschreibung'], 0, 40),
                        'betrag' => $beleg['betrag']
                    ];
                }
            } else {
                $konflikte['neue']++;
            }
        }

        return $konflikte;
    }

    private static function analysiereAbrechnungenKonflikte($db, $abrechnungen, $tabelle)
    {
        $konflikte = [
            'duplikate_monat' => 0,
            'neue' => 0
        ];

        foreach ($abrechnungen as $abrechnung) {
            $existierend = $db->table($tabelle)
                ->where('abrechnungsmonat', $abrechnung['abrechnungsmonat'])
                ->get()
                ->getRowArray();

            if ($existierend) {
                $konflikte['duplikate_monat']++;
            } else {
                $konflikte['neue']++;
            }
        }

        return $konflikte;
    }

    private static function generiereImportEmpfehlung($konflikte)
    {
        $gesamtDuplikate =
            $konflikte['buchungen']['duplikate'] +
            $konflikte['belege']['duplikate_belegnummer'];

        $gesamtNeu =
            $konflikte['buchungen']['neue'] +
            $konflikte['belege']['neue'];

        if ($gesamtDuplikate === 0) {
            return [
                'modus' => 'merge',
                'nachricht' => 'Keine Konflikte gefunden - Import ohne Überschreiben empfohlen',
                'risiko' => 'niedrig'
            ];
        } elseif ($gesamtNeu === 0) {
            return [
                'modus' => 'overwrite',
                'nachricht' => 'Alle Daten existieren bereits - Überschreiben empfohlen',
                'risiko' => 'mittel'
            ];
        } else {
            return [
                'modus' => 'merge',
                'nachricht' => "Mix aus neuen ({$gesamtNeu}) und existierenden ({$gesamtDuplikate}) Einträgen - Zusammenführen empfohlen",
                'risiko' => 'mittel'
            ];
        }
    }

    private static function erstelleBackupReadme($metadaten)
    {
        $readme = "═══════════════════════════════════════════════════════════\n";
        $readme .= "  VDSt KASSENSYSTEM - KOMPLETTES BACKUP\n";
        $readme .= "═══════════════════════════════════════════════════════════\n\n";

        $readme .= "BACKUP-INFORMATIONEN:\n";
        $readme .= str_repeat('─', 60) . "\n";
        $readme .= sprintf("%-25s %s\n", "Erstellt am:", $metadaten['backup_erstellt_am']);
        $readme .= sprintf("%-25s %s\n", "Kassensystem Version:", $metadaten['kassensystem_version']);
        $readme .= sprintf("%-25s %s\n", "PHP Version:", $metadaten['php_version']);
        $readme .= "\n";

        $readme .= "INHALT DIESES BACKUPS:\n";
        $readme .= str_repeat('─', 60) . "\n";
        $readme .= sprintf("%-25s %d Einträge\n", "Buchungen:", $metadaten['statistiken']['buchungen_anzahl']);
        $readme .= sprintf("%-25s %d Einträge\n", "Belege (Metadaten):", $metadaten['statistiken']['belege_anzahl']);
        $readme .= sprintf("%-25s %d Dateien\n", "Beleg-Dateien:", $metadaten['statistiken']['belege_dateien_kopiert']);
        $readme .= sprintf("%-25s %d Dateien\n", "Fehlende Dateien:", $metadaten['statistiken']['belege_dateien_fehlend']);
        $readme .= sprintf("%-25s %d Einträge\n", "AH² Abrechnungen:", $metadaten['statistiken']['ah_abrechnungen']);
        $readme .= sprintf("%-25s %d Einträge\n", "HV Abrechnungen:", $metadaten['statistiken']['hv_abrechnungen']);
        $readme .= sprintf("%-25s %d Verknüpfungen\n", "Abrechnung-Belege:", $metadaten['statistiken']['abrechnung_verknuepfungen']);
        $readme .= "\n";

        $readme .= "KONTOSTÄNDE BEIM EXPORT:\n";
        $readme .= str_repeat('─', 60) . "\n";
        foreach ($metadaten['kontostaende_beim_export'] as $konto => $daten) {
            $kontoName = ucfirst(str_replace('_', ' ', $konto));
            $readme .= sprintf("%-25s %s €\n", $kontoName . ":", number_format($daten['saldo'], 2, ',', '.'));
        }
        $readme .= "\n";

        if (!empty($metadaten['fehlende_dateien'])) {
            $readme .= "⚠️  FEHLENDE BELEG-DATEIEN:\n";
            $readme .= str_repeat('─', 60) . "\n";
            $readme .= "Die folgenden Belege sind in der Datenbank vorhanden,\n";
            $readme .= "aber die Dateien wurden nicht gefunden:\n\n";
            foreach (array_slice($metadaten['fehlende_dateien'], 0, 10) as $belegnr) {
                $readme .= "  - " . $belegnr . "\n";
            }
            if (count($metadaten['fehlende_dateien']) > 10) {
                $readme .= "  ... und " . (count($metadaten['fehlende_dateien']) - 10) . " weitere\n";
            }
            $readme .= "\n";
        }

        $readme .= "DATEI-STRUKTUR:\n";
        $readme .= str_repeat('═', 60) . "\n";
        $readme .= "📄 backup_info.json        - Metadaten des Backups\n";
        $readme .= "📄 buchungen.json          - Alle Kassenbuch-Buchungen\n";
        $readme .= "📄 belege.json             - Beleg-Metadaten\n";
        $readme .= "📄 ah_abrechnungen.json    - AH² Abrechnungen\n";
        $readme .= "📄 hv_abrechnungen.json    - HV Abrechnungen\n";
        $readme .= "📄 abrechnung_belege.json  - Verknüpfungen Abrechnungen↔Belege\n";
        $readme .= "📄 system_einstellungen.json - System-Konfiguration\n";
        $readme .= "📁 beleg_dateien/          - Alle Original-Beleg-Dateien\n";
        $readme .= "📄 README.txt              - Diese Datei\n";
        $readme .= "\n";

        $readme .= "WIEDERHERSTELLUNG:\n";
        $readme .= str_repeat('═', 60) . "\n";
        $readme .= "1. Öffnen Sie das VDSt Kassensystem\n";
        $readme .= "2. Gehen Sie zu 'Kassenbuch'\n";
        $readme .= "3. Klicken Sie auf '📥 Kassenbuch importieren'\n";
        $readme .= "4. Wählen Sie diese ZIP-Datei aus\n";
        $readme .= "5. Prüfen Sie die Vorschau\n";
        $readme .= "6. Wählen Sie Import-Modus:\n";
        $readme .= "   - 'Zusammenführen' = Neue hinzufügen, Bestehende behalten\n";
        $readme .= "   - 'Überschreiben' = Bestehende aktualisieren\n";
        $readme .= "7. Bestätigen Sie den Import\n";
        $readme .= "\n";

        $readme .= "HINWEISE:\n";
        $readme .= str_repeat('─', 60) . "\n";
        $readme .= "• Dieses Backup ist vollständig und portabel\n";
        $readme .= "• Alle JSON-Dateien sind menschenlesbar\n";
        $readme .= "• Original-Datumsstempel werden beim Import beibehalten\n";
        $readme .= "• Belegnummern bleiben erhalten\n";
        $readme .= "• Nach Import werden Kontostände neu berechnet\n";
        $readme .= "• Das System erstellt vor jedem Import automatisch ein Backup\n";
        $readme .= "\n";

        $readme .= "SUPPORT:\n";
        $readme .= str_repeat('─', 60) . "\n";
        $readme .= "VDSt Kassensystem v" . $metadaten['kassensystem_version'] . "\n";
        $readme .= "Digitales Kassenbuch und Abrechnungssystem\n";
        $readme .= "Verein deutscher Studenten\n";

        return $readme;
    }

    private static function addDirectoryToZip($zip, $dir, $zipPath)
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $dir . $file;
            $zipFilePath = $zipPath . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                self::addDirectoryToZip($zip, $filePath . '/', $zipFilePath . '/');
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }

    private static function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}