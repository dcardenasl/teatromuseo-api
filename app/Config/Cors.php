<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     *
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        /**
         * Origins for the `Access-Control-Allow-Origin` header.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Origin
         *
         * E.g.:
         *   - ['http://localhost:8080']
         *   - ['https://www.example.com']
         */
        'allowedOrigins' => [],

        /**
         * Origin regex patterns for the `Access-Control-Allow-Origin` header.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Origin
         *
         * NOTE: A pattern specified here is part of a regular expression. It will
         *       be actually `#\A<pattern>\z#`.
         *
         * E.g.:
         *   - ['https://\w+\.example\.com']
         */
        'allowedOriginsPatterns' => [],

        /**
         * Weather to send the `Access-Control-Allow-Credentials` header.
         *
         * The Access-Control-Allow-Credentials response header tells browsers whether
         * the server allows cross-origin HTTP requests to include credentials.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Credentials
         */
        'supportsCredentials' => false,

        /**
         * Set headers to allow.
         *
         * The Access-Control-Allow-Headers response header is used in response to
         * a preflight request which includes the Access-Control-Request-Headers to
         * indicate which HTTP headers can be used during the actual request.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Headers
         */
        'allowedHeaders' => ['Content-Type', 'Authorization', 'X-App-Key', 'X-Requested-With', 'X-Request-Id', 'Accept', 'Origin'],

        /**
         * Set headers to expose.
         *
         * The Access-Control-Expose-Headers response header allows a server to
         * indicate which response headers should be made available to scripts running
         * in the browser, in response to a cross-origin request.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Expose-Headers
         */
        'exposedHeaders' => [],

        /**
         * Set methods to allow.
         *
         * The Access-Control-Allow-Methods response header specifies one or more
         * methods allowed when accessing a resource in response to a preflight
         * request.
         *
         * E.g.:
         *   - ['GET', 'POST', 'PUT', 'DELETE']
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Allow-Methods
         */
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

        /**
         * Set how many seconds the results of a preflight request can be cached.
         *
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Access-Control-Max-Age
         */
        'maxAge' => 86400,
    ];

    public function __construct()
    {
        parent::__construct();

        $origins = $this->parseCsv((string) env('CORS_ALLOWED_ORIGINS', ''));
        $allowedMethods = $this->parseCsv((string) env('CORS_ALLOWED_METHODS', ''));
        $allowedHeaders = $this->parseCsv((string) env('CORS_ALLOWED_HEADERS', ''));
        $exposedHeaders = $this->parseCsv((string) env('CORS_EXPOSED_HEADERS', ''));

        if ($origins === []) {
            if (ENVIRONMENT === 'production') {
                $appUrl = rtrim((string) env('app.baseURL', ''), '/');
                $origins = $appUrl !== '' ? [$appUrl] : [];
            } else {
                $origins = [
                    'http://localhost:3000',
                    'http://localhost:8080',
                    'http://localhost:5173',
                    'http://127.0.0.1:3000',
                    'http://127.0.0.1:8080',
                ];
            }
        }

        // Defense-in-depth: in production, refuse to boot if CORS would default
        // to an empty origin list. An empty allowedOrigins is closed-by-default
        // in CI4, but the resulting silent 4xx storm is worse than failing loudly.
        // env:check enforces this earlier; this is the runtime backstop.
        if (ENVIRONMENT === 'production' && $origins === []) {
            throw new \RuntimeException(
                'CORS misconfigured for production: set CORS_ALLOWED_ORIGINS '
                . '(comma-separated list) or app.baseURL in .env. Both are empty.'
            );
        }

        if ($allowedMethods !== []) {
            $this->default['allowedMethods'] = $allowedMethods;
        }

        if ($allowedHeaders !== []) {
            $this->default['allowedHeaders'] = $allowedHeaders;
        }

        if ($exposedHeaders !== []) {
            $this->default['exposedHeaders'] = $exposedHeaders;
        }

        $this->default['allowedOrigins'] = $origins;
        $this->default['supportsCredentials'] = filter_var(
            env('CORS_SUPPORTS_CREDENTIALS', false),
            FILTER_VALIDATE_BOOL
        );
        $this->default['maxAge'] = (int) env('CORS_MAX_AGE', $this->default['maxAge']);
    }

    /**
     * @return list<string>
     */
    private function parseCsv(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $items = array_map(static fn (string $item): string => trim($item), explode(',', $value));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }
}
