<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ==================== AUTHENTICATION ROUTES ====================
// Diese Routen sind OHNE Auth-Filter zugänglich

$routes->group('auth', function($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('authenticate', 'AuthController::authenticate');
    $routes->get('logout', 'AuthController::logout');
    $routes->post('refresh', 'AuthController::refreshSession'); // AJAX Session-Refresh
});

// ==================== PROTECTED ROUTES ====================
// Alle anderen Routen benötigen Authentifizierung

$routes->group('', ['filter' => 'auth'], function($routes) {

    // Dashboard (Startseite nach Login)
    $routes->get('/', 'DashboardController::index');
    $routes->get('dashboard', 'DashboardController::index');
    $routes->post('dashboard/quickUpload', 'DashboardController::quickUpload');
    $routes->get('dashboard/status', 'DashboardController::getStatus');

    // ==================== KASSENBUCH ====================
    $routes->group('buchungen', function($routes) {
        $routes->get('/', 'BuchungenController::index');
        $routes->get('create', 'BuchungenController::create');
        $routes->post('store', 'BuchungenController::store');
        $routes->get('edit/(:num)', 'BuchungenController::edit/$1');
        $routes->post('update/(:num)', 'BuchungenController::update/$1');
        $routes->get('delete/(:num)', 'BuchungenController::delete/$1');
        $routes->get('exportExcel', 'BuchungenController::exportExcel');
        $routes->get('getBelegDetails/(:num)', 'BuchungenController::getBelegDetails/$1');
    });

    // ==================== BELEGE ====================
    $routes->group('belege', function($routes) {
        $routes->get('/', 'BelegeController::index');
        $routes->get('create', 'BelegeController::create');
        $routes->post('store', 'BelegeController::store');
        $routes->get('show/(:num)', 'BelegeController::show/$1');
        $routes->get('edit/(:num)', 'BelegeController::edit/$1');
        $routes->post('update/(:num)', 'BelegeController::update/$1');
        $routes->get('delete/(:num)', 'BelegeController::delete/$1');
        $routes->get('download/(:num)', 'BelegeController::download/$1');
        $routes->get('preview/(:num)', 'BelegeController::preview/$1');
        $routes->get('export/excel', 'BelegeController::exportExcel');
        $routes->get('export/zip', 'BelegeController::exportZip');
    });


    // ==================== AH² ABRECHNUNGEN ====================
    $routes->group('abrechnungen/ah', function($routes) {
        $routes->get('/', 'AhAbrechnungenController::index');
        $routes->get('create', 'AhAbrechnungenController::create');
        $routes->post('store', 'AhAbrechnungenController::store');
        $routes->get('belege/(:num)', 'AhAbrechnungenController::selectBelege/$1');
        $routes->post('addBeleg/(:num)', 'AhAbrechnungenController::addBeleg/$1');
        $routes->post('removeBeleg/(:num)', 'AhAbrechnungenController::removeBeleg/$1');
        $routes->get('preview/(:num)', 'AhAbrechnungenController::preview/$1');
        $routes->post('changeStatus/(:num)', 'AhAbrechnungenController::changeStatus/$1');
        $routes->get('exportExcel/(:num)', 'AhAbrechnungenController::exportExcel/$1');
        $routes->get('delete/(:num)', 'AhAbrechnungenController::delete/$1');
    });

    // ==================== HV ABRECHNUNGEN ====================
    $routes->group('abrechnungen/hv', function($routes) {
        $routes->get('/', 'HvAbrechnungenController::index');
        $routes->get('create', 'HvAbrechnungenController::create');
        $routes->post('store', 'HvAbrechnungenController::store');
        $routes->get('belege/(:num)', 'HvAbrechnungenController::selectBelege/$1');
        $routes->post('addBeleg/(:num)', 'HvAbrechnungenController::addBeleg/$1');
        $routes->post('removeBeleg/(:num)', 'HvAbrechnungenController::removeBeleg/$1');
        $routes->get('edit/(:num)', 'HvAbrechnungenController::edit/$1');
        $routes->post('update/(:num)', 'HvAbrechnungenController::update/$1');
        $routes->get('preview/(:num)', 'HvAbrechnungenController::preview/$1');
        $routes->post('changeStatus/(:num)', 'HvAbrechnungenController::changeStatus/$1');
        $routes->get('exportExcel/(:num)', 'HvAbrechnungenController::exportExcel/$1');
        $routes->get('delete/(:num)', 'HvAbrechnungenController::delete/$1');
    });

    // ==================== LEGACY ROUTES ====================
    // Fallback für direkte Abrechnungs-Zugriffe
    $routes->get('abrechnungen', 'AhAbrechnungenController::index');
});

// ==================== ERROR HANDLING ====================
// 404 Error Route
$routes->set404Override(function() {
    if (service('request')->isAJAX()) {
        return service('response')->setJSON(['error' => 'Seite nicht gefunden'])->setStatusCode(404);
    }

    // Prüfe Authentifizierung für 404-Seite
    $authController = new \App\Controllers\AuthController();
    if (!$authController->isAuthenticated()) {
        return redirect()->to('/auth/login');
    }

    return view('errors/html/error_404');
});

// ==================== CLI ROUTES ====================
// Für eventuelle CLI-Kommandos (falls benötigt)
if (is_cli()) {
    $routes->cli('kassensystem/backup', 'CLIController::backup');
    $routes->cli('kassensystem/cleanup', 'CLIController::cleanup');
}

// ZIP-Downloads für Abrechnungen
$routes->get('abrechnungen/ah/downloadZip/(:num)', 'AhAbrechnungenController::downloadBelegeZip/$1');
$routes->get('abrechnungen/hv/downloadZip/(:num)', 'HvAbrechnungenController::downloadBelegeZip/$1');

// Beleg-Management AJAX-Routen
$routes->post('abrechnungen/ah/addBeleg/(:num)', 'AhAbrechnungenController::addBeleg/$1');
$routes->post('abrechnungen/ah/removeBeleg/(:num)', 'AhAbrechnungenController::removeBeleg/$1');
$routes->post('abrechnungen/hv/addBeleg/(:num)', 'HvAbrechnungenController::addBeleg/$1');
$routes->post('abrechnungen/hv/removeBeleg/(:num)', 'HvAbrechnungenController::removeBeleg/$1');

// Lösch-Routen
$routes->get('abrechnungen/ah/delete/(:num)', 'AhAbrechnungenController::delete/$1');
$routes->get('abrechnungen/hv/delete/(:num)', 'HvAbrechnungenController::delete/$1');

// ZIP-Download Routen
$routes->get('abrechnungen/ah/downloadZip/(:num)', 'AhAbrechnungenController::downloadBelegeZip/$1');
$routes->get('abrechnungen/hv/downloadZip/(:num)', 'HvAbrechnungenController::downloadBelegeZip/$1');