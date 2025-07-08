<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration für VDST Kassensystem
 * Unterstützt sowohl XAMPP (lokal) als auch Docker
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'root',
        'password'     => '', // XAMPP Standard: leer
        'database'     => 'vdst_kassensystem_small',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true, // In Produktion auf false setzen
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * Docker database connection.
     */
    public array $docker = [
        'DSN'          => '',
        'hostname'     => 'kassensystem-db', // Docker Service Name
        'username'     => 'kassenuser',
        'password'     => 'kassenpass123',
        'database'     => 'vdst_kassensystem_small',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * Test database connection für PHPUnit Tests.
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => 'localhost',
        'username'    => 'root',
        'password'    => '',
        'database'    => 'vdst_kassensystem_test',
        'DBDriver'    => 'MySQLi',
        'DBPrefix'    => 'test_',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8mb4',
        'DBCollat'    => 'utf8mb4_general_ci',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
        'numberNative' => false,
    ];

    public function __construct()
    {
        parent::__construct();

        // Docker-Erkennung: Prüfe ob wir in Docker laufen
        $isDocker = $this->isRunningInDocker();

        if ($isDocker) {
            // Verwende Docker-Konfiguration
            $this->defaultGroup = 'docker';
            log_message('info', 'Docker-Umgebung erkannt - verwende Docker-Datenbankverbindung');
        } else {
            // Verwende Standard XAMPP-Konfiguration
            $this->defaultGroup = 'default';
            log_message('info', 'Lokale Umgebung erkannt - verwende XAMPP-Datenbankverbindung');
        }

        // Automatisch Test-Datenbank verwenden wenn Tests laufen
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }

        // In Development-Modus mehr Debug-Infos
        if (ENVIRONMENT === 'development') {
            $this->default['DBDebug'] = true;
            $this->docker['DBDebug'] = true;
        }

        // In Production weniger Debug-Infos
        if (ENVIRONMENT === 'production') {
            $this->default['DBDebug'] = false;
            $this->docker['DBDebug'] = false;
            $this->default['pConnect'] = true; // Persistent connections in Production
            $this->docker['pConnect'] = true;
        }
    }

    /**
     * Prüft ob die Anwendung in Docker läuft
     *
     * @return bool
     */
    private function isRunningInDocker(): bool
    {
        // Methode 1: Prüfe auf Docker-spezifische Umgebungsvariablen
        if (getenv('DB_HOST') === 'kassensystem-db') {
            return true;
        }

        // Methode 2: Prüfe auf Docker-Container-Hostname
        if (gethostname() && strpos(gethostname(), 'kassensystem') !== false) {
            return true;
        }

        // Methode 3: Prüfe auf /.dockerenv Datei
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // Methode 4: Prüfe /proc/1/cgroup für Docker
        if (file_exists('/proc/1/cgroup')) {
            $content = file_get_contents('/proc/1/cgroup');
            if (strpos($content, 'docker') !== false) {
                return true;
            }
        }

        return false;
    }
}