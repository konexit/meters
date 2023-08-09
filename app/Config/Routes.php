<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Login');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
// $routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$host = parse_url($_ENV['app.baseURL'])['host'];

// switch the site to development mode
//$routes->get('/', 'Meters::plug');



$routes->get('/', 'Meters::index');

$routes->post('telegram', 'Meters::telegram', ['hostname' => $host]);
$routes->post('telegramUpdates', 'Meters::telegramUpdates', ['hostname' => $host]);

$routes->post('login', 'Meters::login');
$routes->post('logout', 'Meters::logout');
$routes->post('password', 'Meters::password', ['hostname' => $host]);
$routes->get('meters', 'Meters::meters', ['hostname' => $host]);
$routes->post('ajax', 'Meters::ajax', ['hostname' => $host]);
$routes->post('updateDB', 'Meters::updateDB', ['hostname' => $host]);


/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
