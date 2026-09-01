<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

// API Keys — read open to anyone with apikeys.read; mutations require apikeys.write.
$routes->group('api-keys', ['filter' => ['jwtauth', 'throttle']], function ($routes): void {
    $routes->group('', ['filter' => 'permission:apikeys.read'], function ($routes): void {
        $routes->get('', '\App\Controllers\Api\V1\Admin\ApiKeyController::index');
        $routes->get('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::show/$1');
    });
    $routes->group('', ['filter' => 'permission:apikeys.write'], function ($routes): void {
        $routes->post('', '\App\Controllers\Api\V1\Admin\ApiKeyController::create');
        $routes->put('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::update/$1');
        $routes->delete('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::delete/$1');
    });
});

// Metrics
$routes->group('metrics', ['filter' => ['jwtauth', 'permission:metrics.read', 'throttle', 'featureToggle:metrics']], function ($routes): void {
    $routes->get('', '\App\Controllers\Api\V1\Admin\MetricsController::index');
    $routes->get('requests', '\App\Controllers\Api\V1\Admin\MetricsController::requests');
    $routes->get('slow-requests', '\App\Controllers\Api\V1\Admin\MetricsController::slowRequests');
    $routes->get('timeseries', '\App\Controllers\Api\V1\Admin\MetricsController::timeseries');
    $routes->get('workspace', '\App\Controllers\Api\V1\Admin\MetricsController::workspace');
    $routes->get('custom/(:segment)', '\App\Controllers\Api\V1\Admin\MetricsController::custom/$1');
    $routes->post('record', '\App\Controllers\Api\V1\Admin\MetricsController::record');
});

// Audit
$routes->group('audit', ['filter' => ['jwtauth', 'permission:audit.read', 'throttle']], function ($routes): void {
    $routes->get('', '\App\Controllers\Api\V1\Admin\AuditController::index');
    $routes->get('(:num)', '\App\Controllers\Api\V1\Admin\AuditController::show/$1');
    $routes->get('entity/(:segment)/(:num)', '\App\Controllers\Api\V1\Admin\AuditController::byEntity/$1/$2');
});
