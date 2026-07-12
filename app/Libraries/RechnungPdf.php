<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * RechnungPdf - VDSt-gebrandete Getränkerechnungen als PDF (Issue #35)
 *
 * Drei Varianten aus einem geteilten Template (app/Views/pdf/rechnung.php,
 * gesteuert über $typ wie bei den geteilten abrechnungen/*-Views):
 * - coleurBund: Beleg-Rechnung für die AH-Abrechnung (Coleur bzw. Bund)
 * - uebersicht: allgemeine Monats-Übersicht aller Personen (Aushang)
 * - einzel: personalisierte Rechnung für den E-Mail-Versand
 *
 * Alle Methoden liefern die PDF-Bytes als String — der Aufrufer entscheidet
 * über Download, Datei oder E-Mail-Anhang.
 */
class RechnungPdf
{
    /**
     * Rechnung für die AH-Abrechnung (Label 'Coleur' oder 'Bund')
     */
    public function coleurBund(string $label, string $monatsName, float $betrag, string $datum): string
    {
        return $this->render([
            'typ' => 'coleur_bund',
            'label' => $label,
            'monats_name' => $monatsName,
            'betrag' => $betrag,
            'datum' => $datum,
        ]);
    }

    /**
     * Monats-Übersicht aller Personen (für den Aushang)
     *
     * @param array<array{person: string, betrag: float|string}> $personen
     */
    public function uebersicht(string $monatsName, array $personen, string $datum): string
    {
        return $this->render([
            'typ' => 'uebersicht',
            'monats_name' => $monatsName,
            'personen' => $personen,
            'datum' => $datum,
        ]);
    }

    /**
     * Personalisierte Einzelrechnung (E-Mail-Anhang)
     */
    public function einzel(string $person, string $monatsName, float $betrag, string $datum): string
    {
        return $this->render([
            'typ' => 'einzel',
            'person' => $person,
            'monats_name' => $monatsName,
            'betrag' => $betrag,
            'datum' => $datum,
        ]);
    }

    /**
     * ASCII-sicherer PDF-Dateiname: 'Getraenkerechnung' + Teile,
     * Umlaute transliteriert, Leerzeichen als Unterstrich.
     */
    public static function dateiname(string ...$teile): string
    {
        $name = implode(' ', array_merge(['Getraenkerechnung'], $teile));
        $name = strtr($name, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ]);
        $name = preg_replace('/\s+/', '_', trim($name));
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $name);

        return $name . '.pdf';
    }

    /**
     * Rendert das geteilte Template und lässt dompdf ein A4-PDF erzeugen.
     */
    private function render(array $daten): string
    {
        $daten['logo_data_uri'] = $this->logoDataUri();

        $html = view('pdf/rechnung', $daten);

        $options = new Options();
        $options->set([
            // DejaVu Sans ist bei dompdf gebündelt und deckt Umlaute + € ab
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            // vendor/ ist evtl. nicht www-data-beschreibbar → Cache nach writable/
            'fontCache' => WRITEPATH . 'cache/',
            'tempDir' => WRITEPATH . 'cache/',
            'chroot' => APPPATH,
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Vereinslogo als Data-URI (kein Dateizugriff im dompdf-Kontext nötig);
     * leerer String, falls das Asset fehlt — das Template lässt das Bild
     * dann einfach weg.
     */
    private function logoDataUri(): string
    {
        $pfad = FCPATH . 'img/vdst-logo.svg';

        if (!is_file($pfad)) {
            return '';
        }

        return 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($pfad));
    }
}
