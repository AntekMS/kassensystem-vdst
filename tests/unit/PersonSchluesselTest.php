<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet den kanonischen Personen-Schlüssel aus dem label_helper (Issue #35).
 *
 * Schreib- und Lesepfad des Rechnungsversands müssen Namen identisch
 * normalisieren, sonst finden sich gespeicherte E-Mail-Adressen nicht wieder
 * (Whitespace-/Case-Mismatch zwischen person_emails und schulden.person).
 *
 * @internal
 */
final class PersonSchluesselTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('label');
    }

    public function testNormalisiereTrimmtUndKollabiertWhitespace(): void
    {
        $this->assertSame('Hans Meier', person_normalisiere('  Hans   Meier '));
        $this->assertSame('Hans Meier', person_normalisiere("Hans\tMeier"));
        $this->assertSame('Max Mustermann', person_normalisiere('Max Mustermann'));
    }

    public function testSchluesselIstCaseInsensitivUndWhitespaceStabil(): void
    {
        // Der springende Punkt: derselbe Name mit Doppel-Leerzeichen bzw.
        // abweichender Groß-/Kleinschreibung ergibt denselben Schlüssel.
        $this->assertSame(
            person_schluessel('Hans  Meier'),
            person_schluessel('hans meier')
        );
        $this->assertSame('hans meier', person_schluessel('  Hans   Meier '));
    }

    public function testSchluesselTrenntVerschiedeneNamen(): void
    {
        $this->assertNotSame(
            person_schluessel('Hans Meier'),
            person_schluessel('Hans Müller')
        );
    }

    public function testSchluesselMitSonderzeichenBleibtStabil(): void
    {
        // Namen mit Klammern (z.B. Gäste) dürfen den Schlüssel nicht zerlegen.
        $this->assertSame('meier [gast]', person_schluessel('Meier [Gast]'));
    }

    public function testAnkerIstIdSicherUndConsistent(): void
    {
        // Issue #55: Anker fürs Scroll-Ziel muss id-/fragment-tauglich sein
        // (keine Leerzeichen/Klammern/Umlaute) und mit person_anker-Prefix.
        $this->assertSame('person-hans-meier', person_anker('Hans Meier'));
        $this->assertSame('person-meier-gast', person_anker('Meier [Gast]'));
        // Whitespace- und Case-Varianten desselben Namens → gleicher Anker.
        $this->assertSame(person_anker('Hans  Meier'), person_anker('hans meier'));
    }

    public function testAnkerTrenntVerschiedeneNamen(): void
    {
        $this->assertNotSame(person_anker('Hans Meier'), person_anker('Hans Müller'));
    }
}
