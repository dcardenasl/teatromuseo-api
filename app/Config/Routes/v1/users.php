<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('users', ['filter' => ['jwtauth', 'throttle']], function ($routes): void {
    // Static path comes BEFORE the (:num) route so it isn't consumed as an id.
    $routes->get('assignable-roles', '\App\Controllers\Api\V1\Users\UserController::assignableRoles', [
        'filter' => 'permission:users.write',
    ]);

    $routes->get('(:num)', '\App\Controllers\Api\V1\Users\UserController::show/$1');

    // Admin only — uses fine-grained permissions (users.read for list, users.write for mutations)
    $routes->group('', ['filter' => 'permission:users.read'], function ($routes): void {
        $routes->get('', '\App\Controllers\Api\V1\Users\UserController::index');
    });
    $routes->group('', ['filter' => 'permission:users.write'], function ($routes): void {
        $routes->post('', '\App\Controllers\Api\V1\Users\UserController::create');
        $routes->put('(:num)', '\App\Controllers\Api\V1\Users\UserController::update/$1');
        $routes->delete('(:num)', '\App\Controllers\Api\V1\Users\UserController::delete/$1');
        $routes->post('(:num)/approve', '\App\Controllers\Api\V1\Users\UserController::approve/$1');
    });
});
