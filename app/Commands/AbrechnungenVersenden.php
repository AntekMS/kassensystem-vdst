<?php

namespace App\Commands;

use App\Helpers\ZipHelper;
use App\Libraries\RechnungVersand;
use App\Models\AhAbrechnungModel;
use App\Models\HvAbrechnungModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * AbrechnungenVersenden - Automatischer Monats-Versand der Abrechnungen (Issue #37)
 *
 * Schickt die offene AH- und HV-Abrechnung als Komplett-ZIP (Excel + alle Belege)
 * per E-Mail an den Kassenwart selbst (vdst.kassenwart_email), der sie prüft und
 * weiterleitet. Der Abrechnungs-Status bleibt bewusst UNVERÄNDERT.
 *
 * Gedacht für einen Host-Cron zum Monatsende (Muster wie scripts/backup.sh):
 *   docker exec kassensystem-vdst-web php spark abrechnungen:versenden
 *
 * Ohne SMTP-Konfiguration (email.*) oder ohne vdst.kassenwart_email passiert
 * nichts — der Befehl bricht sauber mit Hinweis ab.
 */
class AbrechnungenVersenden extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'abrechnungen:versenden';
    protected $description  = 'Schickt die offene AH-/HV-Abrechnung als ZIP (Excel + Belege) per E-Mail an den Kassenwart (Issue #37).';
    protected $usage       = 'abrechnungen:versenden';

    public function run(array $params)
    {
        $empfaenger = trim((string) env('vdst.kassenwart_email', ''));

        if (! RechnungVersand::istKonfiguriert()) {
            CLI::write('E-Mail-Versand ist nicht konfiguriert (email.* in .env fehlt) — nichts zu tun.', 'yellow');

            return;
        }

        if ($empfaenger === '') {
            CLI::write('Kein Empfänger gesetzt (vdst.kassenwart_email in .env fehlt) — nichts zu tun.', 'yellow');

            return;
        }

        $versand = new RechnungVersand();
        $typen   = [
            ['ah', new AhAbrechnungModel(), 'AH²'],
            ['hv', new HvAbrechnungModel(), 'Heimverein'],
        ];

        $verschickt = 0;

        foreach ($typen as [$typ, $model, $typName]) {
            $abrechnung = $model->findeOffeneAbrechnung();
            $belege     = $abrechnung ? $model->getBelege($abrechnung['id']) : [];

            if (! self::sollVersenden($abrechnung, $belege)) {
                CLI::write($typName . ': keine offene Abrechnung mit Belegen — übersprungen.', 'dark_gray');

                continue;
            }

            try {
                [$inhalt, $dateiname, $mime] = $this->baueAnhang($typ, $abrechnung, $belege);
                $monatsName                  = $model->getMonatName($abrechnung['abrechnungsmonat']);
                $mail                        = RechnungVersand::baueAbrechnungMail($typName, $monatsName);

                if ($versand->sende($empfaenger, $mail['betreff'], $mail['text'], $inhalt, $dateiname, $mime)) {
                    CLI::write($typName . '-Abrechnung ' . $monatsName . ' an ' . $empfaenger . ' verschickt.', 'green');
                    $verschickt++;
                } else {
                    CLI::error($typName . '-Abrechnung ' . $monatsName . ': Versand fehlgeschlagen (siehe Log).');
                }
            } catch (\Throwable $e) {
                log_message('error', 'Auto-Abrechnungsversand ({typ}) fehlgeschlagen: {msg}', [
                    'typ' => $typ,
                    'msg' => $e->getMessage(),
                ]);
                CLI::error($typName . ': ' . $e->getMessage());
            }
        }

        CLI::write($verschickt . ' Abrechnung(en) verschickt.', 'white');
    }

    /**
     * Reiner Seam: verschickt wird nur eine existierende offene Abrechnung mit
     * mindestens einem Beleg — leere/fehlende Abrechnungen werden übersprungen.
     * DB-los testbar.
     */
    public static function sollVersenden(?array $abrechnung, array $belege): bool
    {
        return $abrechnung !== null && $belege !== [];
    }

    /**
     * Baut den Mail-Anhang. Aktuell Komplett-ZIP (Excel + Belege). Bewusst in
     * EINER Methode gekapselt: der laut Issue #83 geplante Wechsel auf eine
     * echte PDF-Rechnung soll hier eine lokale Änderung bleiben.
     *
     * @return array{0: string, 1: string, 2: string} [inhalt, dateiname, mime]
     */
    private function baueAnhang(string $typ, array $abrechnung, array $belege): array
    {
        ZipHelper::cleanupTempZips();
        $zipPath = ZipHelper::erstelleBelegeZip($abrechnung, $belege, $typ);

        if (! $zipPath || ! is_file($zipPath)) {
            throw new \RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
        }

        $inhalt = file_get_contents($zipPath);
        @unlink($zipPath);

        $dateiname = strtoupper($typ) . '_Abrechnung_' . $abrechnung['abrechnungsmonat'] . '.zip';

        return [$inhalt, $dateiname, 'application/zip'];
    }
}
