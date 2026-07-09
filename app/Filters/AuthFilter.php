<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthFilter - Schützt alle Seiten vor unbefugtem Zugriff
 *
 * Wie ein Wächter vor dem Tresor:
 * - Prüft bei jeder Seite die Authentifizierung
 * - Leitet zu Login weiter falls nicht angemeldet
 * - Ausnahmen für Login-Seiten
 */
class AuthFilter implements FilterInterface
{
    /**
     * Prüft Authentifizierung vor jeder geschützten Seite
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Aktuelle URI holen (ohne führende/abschließende Slashes für exakten Vergleich)
        $currentPath = trim(service('uri')->getPath(), '/');

        // Ausnahmen: Diese Pfade sind ohne Login zugänglich
        $publicPaths = [
            'auth/login',
            'auth/authenticate'
        ];

        // Prüfen ob aktueller Pfad öffentlich ist (exakter Match statt Teilstring)
        if (in_array($currentPath, $publicPaths, true)) {
            return; // Zugriff erlaubt
        }

        // Authentifizierung prüfen
        if (!\App\Libraries\Auth::istAngemeldet()) {
            // Session-Message setzen
            session()->setFlashdata('error', 'Bitte melden Sie sich zuerst an.');

            // Zu Login weiterleiten
            return redirect()->to('/auth/login');
        }

        // Session bei Aktivität verlängern
        session()->set('login_time', time());
    }

    /**
     * Nach der Anfrage (wird hier nicht benötigt)
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nichts zu tun
    }
}