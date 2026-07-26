<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

/**
 * Internal M2M routes — accessible only via X-App-Key.
 *
 * These endpoints are for trusted Domain apps to call Hub services
 * (email queuing, etc.) without exposing them to the public internet.
 * Authentication is via the `appKeyRequired` filter (same as public routes).
 */
$routes->group('internal', ['filter' => ['appKeyRequired', 'throttle']], function ($routes): void {
    $routes->post('email/queue', '\App\Controllers\Api\V1\Internal\InternalEmailController::queue');
    $routes->get('files/batch-meta', '\App\Controllers\Api\V1\Internal\InternalFileMetaController::batchMeta');
});
