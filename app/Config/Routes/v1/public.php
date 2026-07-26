<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

/**
 * --------------------------------------------------------------------
 * Public API Routes (X-App-Key authenticated)
 * --------------------------------------------------------------------
 *
 * Endpoints exposed to a public web/mobile frontend that does NOT have a
 * logged-in user. Authentication is via the `X-App-Key` header validated by
 * AppKeyRequiredFilter (alias `appKeyRequired`).
 *
 * Convention:
 *   - JWT-authenticated user routes  → their domain file (auth.php, files.php, …)
 *   - Public, app-key only routes    → THIS file
 *
 * The `appKeyRequired` filter returns 401 if the header is missing and 403 if
 * the key is unknown or revoked, per RFC 7235. `throttle` keeps the public
 * surface from being scraped without limits.
 *
 * Replace the ping example below with the real public endpoints for your app.
 */
$routes->group('public', ['filter' => ['appKeyRequired', 'throttle']], static function ($routes): void {
    $routes->get('ping', static function () {
        return service('response')
            ->setStatusCode(200)
            ->setJSON([
                'status'    => 'ok',
                'timestamp' => date(DATE_ATOM),
            ]);
    });
});
