<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/**
 * --------------------------------------------------------------------
 * API Information
 * --------------------------------------------------------------------
 */

// Swagger UI — disabled in production to avoid exposing API schema publicly
if (ENVIRONMENT !== 'production') {
    $routes->get('/api/docs', static function () {
        $swaggerJsonUrl = base_url('swagger.json');
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>API Docs</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
            <div id="swagger-ui"></div>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: "{$swaggerJsonUrl}",
                    dom_id: '#swagger-ui',
                    presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
                    layout: 'BaseLayout',
                    deepLinking: true,
                });
            </script>
            </body>
            </html>
            HTML;
    });
}

$routes->get('/', static function () {
    return response()->setJSON([
        'name'        => \Config\Project::NAME,
        'version'     => \Config\Project::VERSION,
        'description' => \Config\Project::DESCRIPTION,
        'documentation' => [
            'openapi' => base_url('swagger.json'),
            'github'  => 'https://github.com/david-cardenas/ci4-api-starter',
        ],
        'timestamp' => date('Y-m-d H:i:s'),
    ])->setStatusCode(200);
});

/**
 * Public version discovery endpoint (audit B7.2 / ADR-008).
 *
 * Lets clients machine-read the supported API versions and their lifecycle
 * status (current / deprecated / sunset). Pairs with `DeprecationHeadersFilter`
 * which annotates per-request responses; this endpoint is the bulk catalog.
 */
$routes->get('/api/versions', static function () {
    /** @var \Config\Api $apiConfig */
    $apiConfig = config('Api');
    $current = null;
    $versions = [];

    foreach ($apiConfig->apiVersions as $key => $entry) {
        $versions[] = array_merge(['version' => $key], $entry);
        if (($entry['status'] ?? null) === 'current') {
            $current = $key;
        }
    }

    return response()->setJSON([
        'current'  => $current,
        'versions' => $versions,
    ])->setStatusCode(200);
});

/**
 * --------------------------------------------------------------------
 * Modular Route Loader
 * --------------------------------------------------------------------
 */

// 1. Load System/Health routes at root level
if (file_exists(APPPATH . 'Config/Routes/v1/system.php')) {
    require APPPATH . 'Config/Routes/v1/system.php';
}

// 2. Load Domain routes under api/v1 group
$routes->group('api/v1', function ($routes): void {
    $routesDir = APPPATH . 'Config/Routes/v1';

    if (is_dir($routesDir)) {
        $files = glob($routesDir . '/*.php');
        foreach ($files as $file) {
            // Skip system routes as they are already loaded at root
            if (basename($file) === 'system.php') {
                continue;
            }
            require $file;
        }
    }
});
