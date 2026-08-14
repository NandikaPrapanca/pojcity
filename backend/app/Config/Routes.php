<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

/*
 * API v1 Routes
 */
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Public auth routes
    $routes->post('auth/login', 'AuthController::login');

    // Protected auth routes
    $routes->group('', ['filter' => 'auth'], function ($routes) {
        $routes->get('auth/me', 'AuthController::me');
        $routes->post('auth/logout', 'AuthController::logout');
    });
});
