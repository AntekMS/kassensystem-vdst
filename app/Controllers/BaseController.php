<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = service('session');
    }

    /**
     * Baut den Download-Dateinamen eines Exports aus dem aktiven Filter
     * (Issue #91) — vorher je einmal in Belege- und Buchungen-Controller.
     *
     * Aufbau: {Prefix}_{Datumsblock}_{Filterwerte}_{heute}.{Endung}. Den
     * Datumsblock kennen beide Bereiche gleich; welche weiteren Filter in den
     * Namen wandern, gibt der Aufrufer über $filterSchluessel vor.
     *
     * @param array         $filterSchluessel Filter-Keys, die angehängt werden (Reihenfolge zählt)
     * @param string|null   $leerText         Platzhalter, wenn gar kein Filter aktiv ist (z.B. 'alle')
     */
    protected function exportDateiname(
        string $prefix,
        array $filter,
        string $extension,
        array $filterSchluessel = [],
        ?string $leerText = null
    ): string {
        $parts = [];

        if (!empty($filter['datum_von']) && !empty($filter['datum_bis'])) {
            $parts[] = $filter['datum_von'] . '_bis_' . $filter['datum_bis'];
        } elseif (!empty($filter['datum_von'])) {
            $parts[] = 'ab_' . $filter['datum_von'];
        } elseif (!empty($filter['datum_bis'])) {
            $parts[] = 'bis_' . $filter['datum_bis'];
        }

        foreach ($filterSchluessel as $schluessel) {
            if (!empty($filter[$schluessel])) {
                $parts[] = $filter[$schluessel];
            }
        }

        if ($parts === [] && $leerText !== null) {
            $parts[] = $leerText;
        }

        $name = $prefix . ($parts === [] ? '' : '_' . implode('_', $parts));

        return $name . '_' . date('Y-m-d') . '.' . $extension;
    }
}
