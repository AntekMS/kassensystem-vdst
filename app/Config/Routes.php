<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ==================== AUTHENTICATION ====================
// Diese Routen sind OHNE Auth-Filter zugänglich

$routes->group('auth', function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('authenticate', 'AuthController::authenticate');
    $routes->post('logout', 'AuthController::logout');
});

// ==================== GESCHÜTZTE ROUTEN ====================

$routes->group('', ['filter' => 'auth'], function ($routes) {

    // Dashboard (Startseite nach Login)
    $routes->get('/', 'DashboardController::index');
    $routes->get('dashboard', 'DashboardController::index');

    // ==================== KASSENBUCH ====================
    $routes->group('buchungen', function ($routes) {
        $routes->get('/', 'BuchungenController::index');
        $routes->get('create', 'BuchungenController::create');
        $routes->post('store', 'BuchungenController::store');
        $routes->get('edit/(:num)', 'BuchungenController::edit/$1');
        $routes->post('update/(:num)', 'BuchungenController::update/$1');
        $routes->post('delete/(:num)', 'BuchungenController::delete/$1');
        $routes->get('getBelegDetails/(:num)', 'BuchungenController::getBelegDetails/$1');
        $routes->get('exportExcel', 'BuchungenController::exportExcel');
        $routes->get('export/zip', 'BuchungenController::exportZip');
    });

    // ==================== BELEGE ====================
    $routes->group('belege', function ($routes) {
        $routes->get('/', 'BelegeController::index');
        $routes->get('create', 'BelegeController::create');
        $routes->post('store', 'BelegeController::store');
        $routes->get('show/(:num)', 'BelegeController::show/$1');
        $routes->get('edit/(:num)', 'BelegeController::edit/$1');
        $routes->post('update/(:num)', 'BelegeController::update/$1');
        $routes->post('delete/(:num)', 'BelegeController::delete/$1');
        $routes->get('download/(:num)', 'BelegeController::download/$1');
        $routes->get('preview/(:num)', 'BelegeController::preview/$1');
        $routes->get('export/excel', 'BelegeController::exportExcel');
        $routes->get('export/zip', 'BelegeController::exportZip');
    });

    // ==================== SCHULDEN & INVENTUR ====================
    $routes->group('schulden', function ($routes) {
        $routes->get('/', 'SchuldenController::index');
        $routes->get('person', 'SchuldenController::person');
        $routes->get('create', 'SchuldenController::create');
        $routes->post('store', 'SchuldenController::store');
        $routes->get('edit/(:num)', 'SchuldenController::edit/$1');
        $routes->post('update/(:num)', 'SchuldenController::update/$1');
        $routes->post('delete/(:num)', 'SchuldenController::delete/$1');
        $routes->get('export/inventur', 'SchuldenController::exportInventur');
    });

    // Inventur (eigene Seite, Issue #36; Excel-Export bleibt unter schulden/export/inventur)
    $routes->get('inventur', 'SchuldenController::inventur');

    // ==================== AH²- UND HV-ABRECHNUNGEN ====================
    foreach (['ah' => 'AhAbrechnungenController', 'hv' => 'HvAbrechnungenController'] as $typ => $controller) {
        $routes->group("abrechnungen/{$typ}", function ($routes) use ($controller) {
            $routes->get('/', "{$controller}::index");
            $routes->get('create', "{$controller}::create");
            $routes->post('store', "{$controller}::store");
            $routes->get('belege/(:num)', "{$controller}::selectBelege/$1");
            $routes->post('addBeleg/(:num)', "{$controller}::addBeleg/$1");
            $routes->post('removeBeleg/(:num)', "{$controller}::removeBeleg/$1");
            $routes->get('edit/(:num)', "{$controller}::edit/$1");
            $routes->post('update/(:num)', "{$controller}::update/$1");
            $routes->get('preview/(:num)', "{$controller}::preview/$1");
            $routes->post('changeStatus/(:num)', "{$controller}::changeStatus/$1");
            $routes->get('exportExcel/(:num)', "{$controller}::exportExcel/$1");
            $routes->get('downloadZip/(:num)', "{$controller}::downloadBelegeZip/$1");
            $routes->post('delete/(:num)', "{$controller}::delete/$1");
        });
    }

    // Fallback für direkte Abrechnungs-Zugriffe
    $routes->get('abrechnungen', 'AhAbrechnungenController::index');
});

// ==================== ERROR HANDLING ====================

$routes->set404Override(function () {
    // Wichtig: CI4 gibt den Rückgabewert dieser Closure per echo aus. Ein
    // Response-/RedirectResponse-Objekt zurückzugeben würde beim String-Cast
    // fatal fehlschlagen. Daher die geteilte Response mutieren und '' zurückgeben.
    $response = service('response')->setStatusCode(404);

    if (service('request')->isAJAX()) {
        $response->setJSON(['error' => 'Seite nicht gefunden']);
        return $response->getBody();
    }

    if (!\App\Libraries\Auth::istAngemeldet()) {
        $response->redirect(site_url('auth/login'));
        return '';
    }

    return view('errors/html/error_404');
});
