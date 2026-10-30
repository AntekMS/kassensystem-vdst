<?php

use App\Models\BuchungModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Paginierung der Belege-/Kassenbuch-Listen (Issue #105): Bereichsanzeige
 * über dem Pager und die gefilterten Kassenbuch-Summen, die jetzt per SQL
 * über ALLE Treffer statt aus den Zeilen der aktuellen Seite kommen.
 *
 * @internal
 */
final class PaginierungTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    public function testSeitenBereichErsteSeite(): void
    {
        $this->assertSame('1–25 von 112', seiten_bereich_text(1, 25, 112));
    }

    public function testSeitenBereichMittlereSeite(): void
    {
        $this->assertSame('26–50 von 112', seiten_bereich_text(2, 25, 112));
    }

    public function testSeitenBereichKurzeLetzteSeite(): void
    {
        $this->assertSame('101–112 von 112', seiten_bereich_text(5, 25, 112));
    }

    public function testSeitenBereichEinzelnerTreffer(): void
    {
        $this->assertSame('1 von 1', seiten_bereich_text(1, 25, 1));
    }

    public function testSeitenBereichOhneTrefferIstLeer(): void
    {
        $this->assertSame('', seiten_bereich_text(1, 25, 0));
    }

    public function testSeitenBereichSeiteHinterDemEndeIstLeer(): void
    {
        // Kommt in der App nicht vor (der Pager kappt ?page), die pure
        // Funktion darf trotzdem keinen unsinnigen Bereich wie „201–40" bauen
        $this->assertSame('', seiten_bereich_text(9, 25, 40));
    }

    public function testKassenbuchSummenAusAggregatZeilen(): void
    {
        $summen = BuchungModel::summenAusAggregat([
            ['buchungsart' => 'einnahme', 'summe' => '150.50'],
            ['buchungsart' => 'ausgabe', 'summe' => '40.25'],
        ]);

        $this->assertSame(150.50, $summen['einnahmen']);
        $this->assertSame(40.25, $summen['ausgaben']);
        $this->assertSame(110.25, $summen['saldo']);
    }

    public function testKassenbuchSummenOhneTrefferSindNull(): void
    {
        $summen = BuchungModel::summenAusAggregat([]);

        $this->assertSame(['einnahmen' => 0.0, 'ausgaben' => 0.0, 'saldo' => 0.0], $summen);
    }

    public function testKassenbuchSummenNurAusgaben(): void
    {
        $summen = BuchungModel::summenAusAggregat([
            ['buchungsart' => 'ausgabe', 'summe' => '12.00'],
        ]);

        $this->assertSame(0.0, $summen['einnahmen']);
        $this->assertSame(-12.0, $summen['saldo']);
    }
}
