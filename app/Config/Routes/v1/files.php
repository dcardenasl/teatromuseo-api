<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('files', ['filter' => ['jwtauth', 'throttle']], function ($routes): void {
    $routes->get('', '\App\Controllers\Api\V1\Files\FileController::index');
    $routes->post('upload', '\App\Controllers\Api\V1\Files\FileController::upload');
    $routes->post('bulk-delete', '\App\Controllers\Api\V1\Files\FileController::bulkDelete');
    $routes->post('bulk-restore', '\App\Controllers\Api\V1\Files\FileController::bulkRestore');
    $routes->post('bulk-force-delete', '\App\Controllers\Api\V1\Files\FileController::bulkForceDelete');
    $routes->get('(:num)/info', '\App\Controllers\Api\V1\Files\FileController::info/$1');
    $routes->get('(:num)/usages', '\App\Controllers\Api\V1\Files\FileController::usages/$1');
    $routes->post('(:num)/regenerate-variants', '\App\Controllers\Api\V1\Files\FileController::regenerateVariants/$1');
    $routes->post('(:num)/replace', '\App\Controllers\Api\V1\Files\FileController::replace/$1');
    $routes->patch('(:num)', '\App\Controllers\Api\V1\Files\FileController::metadata/$1');
    $routes->get('(:num)', '\App\Controllers\Api\V1\Files\FileController::show/$1');
    $routes->post('(:num)/restore', '\App\Controllers\Api\V1\Files\FileController::restore/$1');
    $routes->delete('(:num)/force', '\App\Controllers\Api\V1\Files\FileController::forceDelete/$1');
    $routes->delete('(:num)', '\App\Controllers\Api\V1\Files\FileController::delete/$1');
});
