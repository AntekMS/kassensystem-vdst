<?php

namespace App\Libraries;

/**
 * Auth - Zentrale Authentifizierungs-Prüfung
 *
 * Einziger Ort für die Session-/Timeout-Logik des Master-Passwort-Logins.
 * Wird von AuthController, AuthFilter und dem 404-Override genutzt, damit
 * die Timeout-Regel nicht mehrfach implementiert wird.
 */
class Auth
{
    /**
     * Prüft, ob der Kassenwart aktuell angemeldet ist (inkl. Session-Timeout).
     * Läuft die Session ab, wird sie hier verworfen.
     */
    public static function istAngemeldet(): bool
    {
        $authenticated = session()->get('kassenwart_authenticated');
        $loginTime = session()->get('login_time');

        // Session-Timeout aus .env oder Standard (8 Stunden)
        $sessionTimeout = env('vdst.session_timeout', 8 * 60 * 60);

        if ($authenticated && $loginTime && (time() - $loginTime) < $sessionTimeout) {
            return true;
        }

        // Session abgelaufen
        if ($authenticated && $loginTime && (time() - $loginTime) >= $sessionTimeout) {
            session()->destroy();
        }

        return false;
    }
}
