<?php

namespace App\Controllers;

use App\Helpers\ExcelHelper;
use App\Libraries\RechnungPdf;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * MusterController - Muster/Vorlagen aller erzeugten Dokumente
 *
 * Zeigt an EINER Stelle, wie jedes vom System erzeugte Dokument aussieht (alle
 * PDFs, alle Excel-Exporte) — ohne echte Belege/Abrechnungen anlegen oder einen
 * Versand auslösen zu müssen. Nutzt AUSSCHLIESSLICH fiktive Platzhalterdaten
 * (fest im Code, keine DB-Zugriffe) und ruft dieselben reinen Generatoren wie
 * der Echtbetrieb (RechnungPdf / ExcelHelper) — es gibt also keine zweite
 * Rendering-Logik, die vom Original abweichen könnte.
 *
 * PDFs werden inline im neuen Tab gezeigt (wie SchuldenController::importEinzelPdf),
 * Excel-Muster als Download (ExcelHelper kann kein Inline). Das Komplett-ZIP hat
 * bewusst kein Muster — es bündelt echte Beleg-Dateien von der Platte.
 */
class MusterController extends BaseController
{
    /**
     * Registry aller Muster — EINZIGE Quelle für Index-Seite UND Dispatch.
     * `art` = 'pdf' (inline) | 'excel' (Download), `gruppe` steuert die
     * Kartengruppierung in der View.
     *
     * @return list<array{slug: string, art: string, gruppe: string, label: string, beschreibung: string}>
     */
    private function muster(): array
    {
        return [
            [
                'slug' => 'einzelrechnung',
                'art' => 'pdf',
                'gruppe' => 'Getränkerechnungen',
                'label' => 'Einzelrechnung Getränke',
                'beschreibung' => 'Personalisierte Rechnung, die beim Versand als E-Mail-Anhang an die einzelne Person geht (mit Getränkedetails).',
            ],
            [
                'slug' => 'uebersicht',
                'art' => 'pdf',
                'gruppe' => 'Getränkerechnungen',
                'label' => 'Übersicht / Aushang',
                'beschreibung' => 'Monatsübersicht aller offenen Getränkebeträge — z.B. zum Aushängen auf dem Haus.',
            ],
            [
                'slug' => 'coleur-bund',
                'art' => 'pdf',
                'gruppe' => 'Getränkerechnungen',
                'label' => 'Coleur- / Bund-Beleg',
                'beschreibung' => 'Beleg-Rechnung für die AH²-Abrechnung, die der Getränke-Import automatisch erzeugt (Anteil Coleur bzw. Bund).',
            ],
            [
                'slug' => 'abrechnung-ah',
                'art' => 'pdf',
                'gruppe' => 'Abrechnungen',
                'label' => 'AH²-Abrechnung (PDF)',
                'beschreibung' => 'Monatsabrechnung an den AH²-Bund als PDF-Rechnung: Belegliste + Gesamtsumme.',
            ],
            [
                'slug' => 'abrechnung-hv',
                'art' => 'pdf',
                'gruppe' => 'Abrechnungen',
                'label' => 'HV-Abrechnung (PDF)',
                'beschreibung' => 'Monatsabrechnung an den Heimverein als PDF-Rechnung, inklusive Freitext-Begründung.',
            ],
            [
                'slug' => 'inventur-pdf',
                'art' => 'pdf',
                'gruppe' => 'Inventur & Kassenbuch',
                'label' => 'Inventur (PDF)',
                'beschreibung' => '„Kassenwart – Aktueller Bestand": Kassenbestand + Forderungen − Verbindlichkeiten als PDF.',
            ],
            [
                'slug' => 'abrechnung-ah-excel',
                'art' => 'excel',
                'gruppe' => 'Abrechnungen',
                'label' => 'AH²-Abrechnung (Excel)',
                'beschreibung' => 'Dieselbe AH²-Monatsabrechnung als Excel-Tabelle (Teil des Excel-/ZIP-Exports).',
            ],
            [
                'slug' => 'abrechnung-hv-excel',
                'art' => 'excel',
                'gruppe' => 'Abrechnungen',
                'label' => 'HV-Abrechnung (Excel)',
                'beschreibung' => 'Dieselbe HV-Monatsabrechnung als Excel-Tabelle, inklusive Begründung.',
            ],
            [
                'slug' => 'kassenbuch',
                'art' => 'excel',
                'gruppe' => 'Inventur & Kassenbuch',
                'label' => 'Kassenbuch (Excel)',
                'beschreibung' => 'Export des Kassenbuchs mit den drei Konten (Aktiven-, Getränke-, Barkasse) im gewohnten Format.',
            ],
            [
                'slug' => 'inventur-excel',
                'art' => 'excel',
                'gruppe' => 'Inventur & Kassenbuch',
                'label' => 'Inventur (Excel)',
                'beschreibung' => 'Dieselbe Inventur „Aktueller Bestand" als Excel-Tabelle.',
            ],
        ];
    }

    public function index()
    {
        // Nach gruppe bündeln, Reihenfolge des ersten Auftretens beibehalten
        $gruppen = [];
        foreach ($this->muster() as $eintrag) {
            $gruppen[$eintrag['gruppe']][] = $eintrag;
        }

        return view('muster/index', ['gruppen' => $gruppen]);
    }

    /**
     * Erzeugt das Muster-Dokument zum Slug — PDF inline im Tab, Excel als
     * Download. Unbekannter Slug → 404.
     */
    public function zeige(string $slug)
    {
        $bekannt = array_column($this->muster(), 'art', 'slug');
        if (!isset($bekannt[$slug])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $datum = date('Y-m-d');

        return match ($bekannt[$slug]) {
            'pdf' => $this->zeigePdf($slug, $datum),
            'excel' => $this->ladeExcel($slug),
        };
    }

    /**
     * PDF inline im Browser (kein Download-Attachment) — analog
     * SchuldenController::importEinzelPdf.
     */
    private function zeigePdf(string $slug, string $datum)
    {
        $pdf = new RechnungPdf();
        $monat = 'Mai 2026';

        $bytes = match ($slug) {
            'einzelrechnung' => $pdf->einzel('Max Mustermann', $monat, 42.50, $datum, $this->getraenkePositionen()),
            'uebersicht' => $pdf->uebersicht($monat, $this->personen(), $datum),
            'coleur-bund' => $pdf->coleurBund('Coleur', $monat, 75.00, $datum, $this->coleurPositionen()),
            'abrechnung-ah' => $pdf->abrechnung('AH²', $monat, $this->abrechnungAh(), $this->belegeAh(), $datum),
            'abrechnung-hv' => $pdf->abrechnung('HV', $monat, $this->abrechnungHv(), $this->belegeHv(), $datum),
            'inventur-pdf' => $pdf->inventur($this->kontostaende(), $this->inventur(), $this->summeKassen(), $this->summeGesamt(), $datum),
        };

        return $this->response
            ->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="Muster_' . $slug . '.pdf"')
            ->setBody($bytes);
    }

    /**
     * Excel-Muster als Download (ExcelHelper kann kein Inline — bewusst).
     */
    private function ladeExcel(string $slug)
    {
        $spreadsheet = match ($slug) {
            'kassenbuch' => ExcelHelper::erstelleKassenbuch($this->buchungen(), $this->kontostaende()),
            'abrechnung-ah-excel' => ExcelHelper::erstelleAhAbrechnung($this->abrechnungAh(), $this->belegeAh()),
            'abrechnung-hv-excel' => ExcelHelper::erstelleHvAbrechnung($this->abrechnungHv(), $this->belegeHv()),
            'inventur-excel' => ExcelHelper::erstelleInventur($this->kontostaende(), $this->inventur()),
        };

        return ExcelHelper::downloadExcel($spreadsheet, 'Muster_' . $slug . '.xlsx');
    }

    // ==================== Fiktive Beispieldaten ====================
    // Bewusst erfundene Namen/Beträge — klar als Muster erkennbar, keine echten
    // Vereinsdaten. Getränkepositionen summieren sich exakt auf den Betrag, sonst
    // verwirft RechnungPdf::positionenFuer sie (Summen-Guard, Issue #63).

    /** @return list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}> */
    private function getraenkePositionen(): array
    {
        return [
            ['bezeichnung' => 'Bier 0,5 l', 'anzahl' => 20.0, 'einzelpreis' => 1.50, 'summe' => 30.00],
            ['bezeichnung' => 'Weinschorle', 'anzahl' => 5.0, 'einzelpreis' => 2.50, 'summe' => 12.50],
        ]; // Summe 42.50
    }

    /** @return list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}> */
    private function coleurPositionen(): array
    {
        return [
            ['bezeichnung' => 'Bier 0,5 l', 'anzahl' => 40.0, 'einzelpreis' => 1.50, 'summe' => 60.00],
            ['bezeichnung' => 'Softdrinks', 'anzahl' => 15.0, 'einzelpreis' => 1.00, 'summe' => 15.00],
        ]; // Summe 75.00
    }

    /** @return list<array{person: string, betrag: float}> */
    private function personen(): array
    {
        return [
            ['person' => 'Max Mustermann', 'betrag' => 42.50],
            ['person' => 'Erika Musterfrau', 'betrag' => 18.00],
            ['person' => 'Hans Beispiel', 'betrag' => 7.50],
            ['person' => 'Gäste', 'betrag' => 5.00],
        ];
    }

    /** @return array{titel: string} */
    private function abrechnungAh(): array
    {
        return ['titel' => 'AH²-Abrechnung Mai 2026', 'gesamtsumme' => 175.00];
    }

    /** @return list<array{beschreibung: string, rechnungsdatum: string, belegnummer: string, betrag: float, lieferant: string}> */
    private function belegeAh(): array
    {
        return [
            ['beschreibung' => 'Getränke Coleur', 'rechnungsdatum' => '2026-05-03', 'belegnummer' => '2026-05-03-001', 'betrag' => 75.00, 'lieferant' => 'Getränkemarkt Meyer'],
            ['beschreibung' => 'Getränke Bund', 'rechnungsdatum' => '2026-05-03', 'belegnummer' => '2026-05-03-002', 'betrag' => 60.00, 'lieferant' => 'Getränkemarkt Meyer'],
            ['beschreibung' => 'Kranzspende', 'rechnungsdatum' => '2026-05-12', 'belegnummer' => '2026-05-12-001', 'betrag' => 40.00, 'lieferant' => 'Blumen Schmidt'],
        ]; // Summe 175.00
    }

    /** @return array{titel: string, begruendung: string, gesamtsumme: float} */
    private function abrechnungHv(): array
    {
        return [
            'titel' => 'HV-Abrechnung Mai 2026',
            'begruendung' => 'Instandhaltung des Verbindungshauses: Reparatur der Heizungsanlage im Keller sowie turnusmäßige Gartenpflege.',
            'gesamtsumme' => 405.00,
        ];
    }

    /** @return list<array{beschreibung: string, rechnungsdatum: string, belegnummer: string, betrag: float, lieferant: string}> */
    private function belegeHv(): array
    {
        return [
            ['beschreibung' => 'Reparatur Heizung', 'rechnungsdatum' => '2026-05-08', 'belegnummer' => '2026-05-08-001', 'betrag' => 320.00, 'lieferant' => 'Haustechnik Wagner'],
            ['beschreibung' => 'Gartenpflege', 'rechnungsdatum' => '2026-05-20', 'belegnummer' => '2026-05-20-002', 'betrag' => 85.00, 'lieferant' => 'Gärtnerei Grün'],
        ]; // Summe 405.00
    }

    /** @return list<array{buchungsdatum: string, beschreibung: string, belegnummer: string, betrag: float, buchungsart: string, konto_typ: string}> */
    private function buchungen(): array
    {
        return [
            ['buchungsdatum' => '2026-05-02', 'beschreibung' => 'Mitgliedsbeitrag Mai', 'belegnummer' => '', 'betrag' => 120.00, 'buchungsart' => 'einnahme', 'konto_typ' => 'aktivenkasse'],
            ['buchungsdatum' => '2026-05-05', 'beschreibung' => 'Getränkeeinkauf', 'belegnummer' => '2026-05-05-001', 'betrag' => 75.00, 'buchungsart' => 'ausgabe', 'konto_typ' => 'getraenkekasse'],
            ['buchungsdatum' => '2026-05-10', 'beschreibung' => 'Getränkeverkauf Kneipe', 'belegnummer' => '', 'betrag' => 95.00, 'buchungsart' => 'einnahme', 'konto_typ' => 'getraenkekasse'],
            ['buchungsdatum' => '2026-05-15', 'beschreibung' => 'Portokosten', 'belegnummer' => '2026-05-15-001', 'betrag' => 8.50, 'buchungsart' => 'ausgabe', 'konto_typ' => 'barkasse'],
        ];
    }

    /** @return array<string, array{saldo: float}> */
    private function kontostaende(): array
    {
        return [
            'aktivenkasse' => ['saldo' => 1250.00],
            'getraenkekasse' => ['saldo' => 340.00],
            'barkasse' => ['saldo' => 85.50],
        ]; // Summe I = 1675.50
    }

    /** @return array<string, array<string, float>> */
    private function inventur(): array
    {
        return [
            'forderung' => ['getraenke' => 42.50, 'abrechnung' => 135.00, 'sonstige' => 0.00, 'summe' => 177.50],
            'verbindlichkeit' => ['getraenke' => 0.00, 'abrechnung' => 0.00, 'sonstige' => 25.00, 'summe' => 25.00],
        ];
    }

    private function summeKassen(): float
    {
        return 1675.50; // I: 1250 + 340 + 85,50
    }

    private function summeGesamt(): float
    {
        return 1828.00; // I + II − III = 1675,50 + 177,50 − 25,00
    }
}
