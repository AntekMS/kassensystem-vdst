<?php

use App\Controllers\SchuldenController;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testet den Same-site-Guard des Begleichen-Redirects (Issue #55).
 *
 * previous_url() fällt bei fehlender Session-URL auf den angreiferbeein-
 * flussbaren HTTP_REFERER zurück; ohne Guard wäre ein Open-Redirect auf einen
 * fremden Host möglich. sameSiteRuecksprungPfad() übernimmt daher nur
 * Path+Query same-site, sonst den sicheren Default /schulden. Reine Funktion
 * (parse_url), daher DB-/Service-los testbar.
 *
 * @internal
 */
final class RedirectSameSiteTest extends CIUnitTestCase
{
    private const HOST = 'example.com';

    private function pfad(?string $kandidat): string
    {
        return SchuldenController::sameSiteRuecksprungPfad($kandidat, self::HOST);
    }

    public function testSameSiteZielBehaeltPfadUndQuery(): void
    {
        $this->assertSame(
            '/schulden?status=offene_getraenke',
            $this->pfad('http://example.com/schulden?status=offene_getraenke')
        );
        $this->assertSame('/schulden/person', $this->pfad('https://example.com/schulden/person'));
    }

    public function testFragmentDesKandidatenWirdVerworfen(): void
    {
        // Der Controller hängt seinen eigenen #person-Anker an — ein vom
        // Referer mitgebrachtes Fragment darf nicht durchsickern.
        $this->assertSame('/schulden', $this->pfad('https://example.com/schulden#person-fremd'));
    }

    public function testFremderHostFaelltAufDefaultZurueck(): void
    {
        $this->assertSame('/schulden', $this->pfad('https://evil.com/phish'));
        // Suffix-Trick: example.com.evil.com ist NICHT example.com.
        $this->assertSame('/schulden', $this->pfad('https://example.com.evil.com/x'));
    }

    public function testProtokollRelativerHostWirdBlockiert(): void
    {
        $this->assertSame('/schulden', $this->pfad('//evil.com/x'));
    }

    public function testLeeresOderUngueltigesZielFaelltAufDefaultZurueck(): void
    {
        $this->assertSame('/schulden', $this->pfad(null));
        $this->assertSame('/schulden', $this->pfad(''));
        $this->assertSame('/schulden', $this->pfad('   '));
    }

    public function testPfadOhneHostFaelltKonservativAufDefaultZurueck(): void
    {
        // Ein Path-only-Referer bringt keinen Host mit → konservativer Default.
        $this->assertSame('/schulden', $this->pfad('/schulden/person?name=Hans'));
    }

    public function testHostVergleichIstCaseInsensitiv(): void
    {
        // Gemischte Groß-/Kleinschreibung gilt als same-site → Pfad+Query
        // bleiben erhalten (nicht der Default).
        $this->assertSame(
            '/schulden/person?name=Hans',
            $this->pfad('https://EXAMPLE.COM/schulden/person?name=Hans')
        );
    }

    public function testLeererEigenerHostFaelltAufDefaultZurueck(): void
    {
        $this->assertSame('/schulden', SchuldenController::sameSiteRuecksprungPfad('https://example.com/schulden', ''));
    }
}
