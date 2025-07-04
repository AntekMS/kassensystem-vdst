<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Basis-Route
$routes->get('/', 'DashboardController::index');

// Dashboard
$routes->group('dashboard', function($routes) {
    $routes->get('/', 'DashboardController::index');
    $routes->post('quickUpload', 'DashboardController::quickUpload');
    $routes->get('getStatus', 'DashboardController::getStatus');
});

// Kassenbuch (Buchungen)
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

// Belege
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
});

// Abrechnungen (Gemeinsam für AH² und HV)
$routes->group('abrechnungen', function($routes) {
    // AH² Abrechnungen
    $routes->get('ah', 'AhAbrechnungenController::index');
    $routes->get('ah/create', 'AhAbrechnungenController::create');
    $routes->post('ah/store', 'AhAbrechnungenController::store');
    $routes->get('ah/belege/(:num)', 'AhAbrechnungenController::selectBelege/$1');
    $routes->post('ah/addBeleg/(:num)', 'AhAbrechnungenController::addBeleg/$1');
    $routes->post('ah/removeBeleg/(:num)', 'AhAbrechnungenController::removeBeleg/$1');
    $routes->get('ah/preview/(:num)', 'AhAbrechnungenController::preview/$1');
    $routes->post('ah/changeStatus/(:num)', 'AhAbrechnungenController::changeStatus/$1');
    $routes->get('ah/exportExcel/(:num)', 'AhAbrechnungenController::exportExcel/$1');
    $routes->get('ah/delete/(:num)', 'AhAbrechnungenController::delete/$1');

    // HV Abrechnungen
    $routes->get('hv', 'HvAbrechnungenController::index');
    $routes->get('hv/create', 'HvAbrechnungenController::create');
    $routes->post('hv/store', 'HvAbrechnungenController::store');
    $routes->get('hv/belege/(:num)', 'HvAbrechnungenController::selectBelege/$1');
    $routes->post('hv/addBeleg/(:num)', 'HvAbrechnungenController::addBeleg/$1');
    $routes->post('hv/removeBeleg/(:num)', 'HvAbrechnungenController::removeBeleg/$1');
    $routes->get('hv/edit/(:num)', 'HvAbrechnungenController::edit/$1');
    $routes->post('hv/update/(:num)', 'HvAbrechnungenController::update/$1');
    $routes->get('hv/preview/(:num)', 'HvAbrechnungenController::preview/$1');
    $routes->post('hv/changeStatus/(:num)', 'HvAbrechnungenController::changeStatus/$1');
    $routes->get('hv/exportExcel/(:num)', 'HvAbrechnungenController::exportExcel/$1');
    $routes->get('hv/delete/(:num)', 'HvAbrechnungenController::delete/$1');
});

// Standard-Route für Abrechnungen zeigt AH²
$routes->get('abrechnungen', 'AhAbrechnungenController::index');