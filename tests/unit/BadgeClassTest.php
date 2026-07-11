<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet die Badge-Klassen-Helper des Design-Systems (Issue #47) —
 * jede Status-/Kategorie-Ausprägung muss auf eine badge-status-Variante
 * aus public/css/app.css abbilden.
 *
 * @internal
 */
final class BadgeClassTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    public function testBelegStatusBadges(): void
    {
        $this->assertSame('badge-status badge-status-neutral', beleg_status_badge_class('erfasst'));
        $this->assertSame('badge-status badge-status-rot', beleg_status_badge_class('in_abrechnung'));
        $this->assertSame('badge-status badge-status-amber', beleg_status_badge_class('abgerechnet'));
        $this->assertSame('badge-status badge-status-gruen', beleg_status_badge_class('bezahlt'));
        // Unbekannte Werte fallen auf neutral zurück
        $this->assertSame('badge-status badge-status-neutral', beleg_status_badge_class('unbekannt'));
        $this->assertSame('badge-status badge-status-neutral', beleg_status_badge_class(null));
    }

    public function testAbrechnungStatusBadges(): void
    {
        $this->assertSame('badge-status badge-status-neutral', abrechnung_status_badge_class('entwurf'));
        $this->assertSame('badge-status badge-status-amber', abrechnung_status_badge_class('ausstehend'));
        $this->assertSame('badge-status badge-status-rot', abrechnung_status_badge_class('eingereicht'));
        $this->assertSame('badge-status badge-status-gruen', abrechnung_status_badge_class('bezahlt'));
        $this->assertSame('badge-status badge-status-neutral', abrechnung_status_badge_class(null));
    }

    public function testKategorieBadges(): void
    {
        $this->assertSame('badge-status badge-status-neutral', kategorie_badge_class('normal'));
        $this->assertSame('badge-status badge-status-outline', kategorie_badge_class('ah_berechtigt'));
        $this->assertSame('badge-status badge-status-outline', kategorie_badge_class('hv_berechtigt'));
        $this->assertSame('badge-status badge-status-neutral', kategorie_badge_class(null));
    }

    public function testBelegStatusBadgesDeckenAlleEnumWerteAb(): void
    {
        foreach (array_keys(beleg_status_optionen()) as $status) {
            $this->assertStringStartsWith('badge-status badge-status-', beleg_status_badge_class($status));
        }
    }
}
