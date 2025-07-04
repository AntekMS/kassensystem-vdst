<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration für VDST Kassensystem
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

        // Automatisch Test-Datenbank verwenden wenn Tests laufen
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }

        // In Development-Modus mehr Debug-Infos
        if (ENVIRONMENT === 'development') {
            $this->default['DBDebug'] = true;
        }

        // In Production weniger Debug-Infos
        if (ENVIRONMENT === 'production') {
            $this->default['DBDebug'] = false;
            $this->default['pConnect'] = true; // Persistent connections in Production
        }
    }
}