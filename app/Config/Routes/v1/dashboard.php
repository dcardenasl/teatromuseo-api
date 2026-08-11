<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('admin/dashboard', ['filter' => ['jwtauth', 'throttle']], function ($routes): void {
    $routes->get('summary', '\App\Controllers\Api\V1\Admin\DashboardSummaryController::index');
});
