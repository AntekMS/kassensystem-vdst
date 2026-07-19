<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SchuldPositionModel - Getränkedetails je Import-Forderung (Issue #63, #59b)
 *
 * Speichert die Einzelpositionen (Menge je Getränk × Einzelpreis) aus der
 * Getränkerechnung-Excel zu einer schulden-Zeile. Reine ANZEIGE-Daten für die
 * PDF-Einzelrechnung — der Forderungsbetrag bleibt autoritativ in
 * schulden.betrag. Die Invariante „Details nur, wenn ihre Summe zum Betrag
 * passt" (nachträglich editierte Forderungen!) prüft summePasst(); der pure
 * Merge-Seam fuegeZusammen() fasst beim (erlaubten) Doppelimport gleiche
 * Positionen mehrerer schulden-Zeilen einer Person zusammen.
 *
 * Löschen der Forderung räumt per FK ON DELETE CASCADE auf.
 */
class SchuldPositionModel extends Model
{
    protected $table = 'schuld_positionen';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['schuld_id', 'bezeichnung', 'anzahl', 'einzelpreis', 'summe', 'sortierung'];

    /**
     * Speichert die Positionen einer Forderung (Reihenfolge = Excel-Spalten,
     * über sortierung festgehalten). Leere Liste = kein Insert.
     *
     * @param list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}> $positionen
     */
    public function speichereFuerSchuld(int $schuldId, array $positionen): void
    {
        $batch = [];
        foreach (array_values($positionen) as $index => $position) {
            $batch[] = [
                'schuld_id' => $schuldId,
                'bezeichnung' => mb_substr($position['bezeichnung'], 0, 100),
                'anzahl' => number_format($position['anzahl'], 2, '.', ''),
                'einzelpreis' => number_format($position['einzelpreis'], 2, '.', ''),
                'summe' => number_format($position['summe'], 2, '.', ''),
                'sortierung' => $index,
            ];
        }

        if ($batch !== []) {
            $this->insertBatch($batch);
        }
    }

    /**
     * Positionen mehrerer schulden-Zeilen, gruppiert nach schuld_id.
     *
     * @param list<int> $schuldIds
     * @return array<int, list<array>>
     */
    public function getFuerSchulden(array $schuldIds): array
    {
        $schuldIds = array_values(array_filter(array_map('intval', $schuldIds)));
        if ($schuldIds === []) {
            return [];
        }

        $gruppen = [];
        foreach ($this->whereIn('schuld_id', $schuldIds)->orderBy('schuld_id')->orderBy('sortierung')->findAll() as $zeile) {
            $gruppen[(int) $zeile['schuld_id']][] = $zeile;
        }

        return $gruppen;
    }

    /**
     * Fasst Positionen (z.B. aus mehreren schulden-Zeilen derselben Person
     * beim Doppelimport) zusammen: gleiche Bezeichnung + Einzelpreis werden
     * zu einer Zeile addiert, die Reihenfolge des ersten Auftretens bleibt.
     *
     * @param list<array{bezeichnung: string, anzahl: float|string, einzelpreis: float|string, summe: float|string}> $positionen
     * @return list<array{bezeichnung: string, anzahl: float, einzelpreis: float, summe: float}>
     */
    public static function fuegeZusammen(array $positionen): array
    {
        $gruppen = [];
        foreach ($positionen as $position) {
            $einzelpreis = (float) $position['einzelpreis'];
            $key = $position['bezeichnung'] . '|' . number_format($einzelpreis, 2, '.', '');

            if (!isset($gruppen[$key])) {
                $gruppen[$key] = [
                    'bezeichnung' => $position['bezeichnung'],
                    'anzahl' => 0.0,
                    'einzelpreis' => $einzelpreis,
                    'summe' => 0.0,
                ];
            }

            $gruppen[$key]['anzahl'] += (float) $position['anzahl'];
            $gruppen[$key]['summe'] = round($gruppen[$key]['summe'] + (float) $position['summe'], 2);
        }

        return array_values($gruppen);
    }

    /**
     * Passen die Positionen (Summe) zum autoritativen Forderungsbetrag?
     * Nein → das PDF zeigt nur die Gesamtsumme (Fallback wie vor Issue #63).
     *
     * @param list<array{summe: float|string}> $positionen
     */
    public static function summePasst(array $positionen, float $betrag): bool
    {
        if ($positionen === []) {
            return false;
        }

        $summe = 0.0;
        foreach ($positionen as $position) {
            $summe += (float) $position['summe'];
        }

        return abs(round($summe, 2) - round($betrag, 2)) < 0.011;
    }
}
