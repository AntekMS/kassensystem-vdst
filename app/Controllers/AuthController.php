<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * AuthController - Einfaches Passwort-System für VDSt Kassensystem
 *
 * Wie ein digitaler Tresor:
 * - Ein Master-Passwort schützt das gesamte System
 * - Session-basiert (kein Benutzer-System nötig)
 * - Klassisches VDSt-Design
 */
class AuthController extends BaseController
{
    // Master-Passwort wird aus .env geladen

    /**
     * Holt Master-Passwort aus .env Datei
     */
    private function getMasterPassword()
    {
        $password = env('vdst.master_password');

        if (empty($password)) {
            log_message('error', 'VDSt Master-Passwort nicht in .env konfiguriert!');
            throw new \RuntimeException('System-Konfigurationsfehler: Master-Passwort nicht gefunden.');
        }

        return $password;
    }
    public function login()
    {
        // Falls bereits eingeloggt, weiterleiten
        if ($this->isAuthenticated()) {
            return redirect()->to('/dashboard');
        }

        $data = [
            'title' => 'Anmeldung',
            'error' => session()->getFlashdata('error')
        ];

        return view('auth/login', $data);
    }

    /**
     * Login verarbeiten
     */
    public function authenticate()
    {
        $password = $this->request->getPost('password');

        // Validation
        if (empty($password)) {
            return redirect()->back()->with('error', 'Bitte geben Sie das Passwort ein.');
        }

        // Passwort aus .env prüfen
        if ($password === $this->getMasterPassword()) {
            // Session setzen mit Kassenwart-Name aus .env
            session()->set([
                'kassenwart_authenticated' => true,
                'kassenwart_name' => env('vdst.kassenwart_name', 'VDSt Kassenwart'),
                'login_time' => time()
            ]);

            return redirect()->to('/dashboard')->with('success', 'Erfolgreich angemeldet!');
        } else {
            // Login-Versuch loggen (für Sicherheit)
            log_message('warning', 'Fehlgeschlagener Login-Versuch von IP: ' . $this->request->getIPAddress());

            return redirect()->back()->with('error', 'Falsches Passwort.');
        }
    }

    /**
     * Logout
     */
    public function logout()
    {
        // Session leeren
        session()->destroy();

        return redirect()->to('/auth/login')->with('success', 'Erfolgreich abgemeldet.');
    }

    /**
     * Prüft ob Benutzer authentifiziert ist
     */
    public function isAuthenticated()
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

    /**
     * Session verlängern (bei Aktivität)
     */
    public function refreshSession()
    {
        if ($this->isAuthenticated()) {
            session()->set('login_time', time());
            return $this->response->setJSON(['success' => true]);
        }

        return $this->response->setJSON(['success' => false]);
    }
}