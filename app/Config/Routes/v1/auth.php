<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

// Public routes
$routes->group('', ['filter' => 'authThrottle'], function ($routes): void {
    $routes->post('auth/login', '\App\Controllers\Api\V1\Auth\AuthController::login');
    $routes->post('auth/google-login', '\App\Controllers\Api\V1\Auth\AuthController::googleLogin');
    $routes->post('auth/register', '\App\Controllers\Api\V1\Auth\AuthController::register');
    $routes->post('auth/refresh', '\App\Controllers\Api\V1\Auth\TokenController::refresh');

    $routes->post('auth/forgot-password', '\App\Controllers\Api\V1\Identity\PasswordResetController::sendResetLink');
    $routes->post('auth/reset-password', '\App\Controllers\Api\V1\Identity\PasswordResetController::resetPassword');
    $routes->get('auth/validate-reset-token', '\App\Controllers\Api\V1\Identity\PasswordResetController::validateToken');

    $routes->get('auth/verify-email', '\App\Controllers\Api\V1\Identity\VerificationController::verify');
    $routes->post('auth/verify-email', '\App\Controllers\Api\V1\Identity\VerificationController::verify');
});

// App-authenticated routes (X-App-Key, no JWT required)
$routes->group('', ['filter' => ['appKeyRequired', 'throttle']], function ($routes): void {
    $routes->post('auth/introspect', '\App\Controllers\Api\V1\Auth\IntrospectController::introspect');
    $routes->post('auth/service-token', '\App\Controllers\Api\V1\Auth\ServiceTokenController::issue');
});

// Protected routes
$routes->group('', ['filter' => ['jwtauth', 'throttle']], function ($routes): void {
    $routes->get('auth/me', '\App\Controllers\Api\V1\Auth\AuthController::me');
    $routes->patch('auth/me', '\App\Controllers\Api\V1\Auth\AuthController::updateMe');
    $routes->post('auth/resend-verification', '\App\Controllers\Api\V1\Identity\VerificationController::resend');
    $routes->post('auth/revoke', '\App\Controllers\Api\V1\Auth\TokenController::revoke');
    $routes->post('auth/revoke-all', '\App\Controllers\Api\V1\Auth\TokenController::revokeAll');
});
