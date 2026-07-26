<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AppKeyRequiredFilter;
use App\Filters\AuthThrottleFilter;
use App\Filters\FeatureToggleFilter;
use App\Filters\JwtAuthFilter;
use App\Filters\PermissionFilter;
use App\Filters\ThrottleFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use dcardenasl\Ci4ApiCore\Http\Filters\CorsFilter;
use dcardenasl\Ci4ApiCore\Http\Filters\LocaleFilter;
use dcardenasl\Ci4ApiCore\Http\Filters\RequestLoggingFilter;
use dcardenasl\Ci4ApiCore\Http\Filters\SecurityHeadersFilter;

class Filters extends BaseFilters
{
    public function __construct()
    {
        parent::__construct();

        // Force HTTPS only in production environment
        if (ENVIRONMENT === 'production') {
            array_unshift($this->required['before'], 'forcehttps');
        }

        // Use TestAuthFilter instead of JwtAuthFilter during tests to simplify identity propagation
        if (ENVIRONMENT === 'testing') {
            $this->aliases['jwtauth'] = \App\Filters\TestAuthFilter::class;
        }
    }

    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecurityHeadersFilter::class,
        'cors'          => CorsFilter::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'jwtauth'       => JwtAuthFilter::class,
        'testauth'      => \App\Filters\TestAuthFilter::class,
        'throttle'      => ThrottleFilter::class,
        'authThrottle'  => AuthThrottleFilter::class,
        'appKeyRequired' => AppKeyRequiredFilter::class,
        'permission'    => PermissionFilter::class,
        'requestLogging' => RequestLoggingFilter::class,
        'locale'        => LocaleFilter::class,
        'featureToggle' => FeatureToggleFilter::class,
        'deprecationheaders' => \dcardenasl\Ci4ApiCore\Http\Filters\DeprecationHeadersFilter::class,
        'idempotency' => \dcardenasl\Ci4ApiCore\Http\Filters\IdempotencyFilter::class,
        'correlationid' => \dcardenasl\Ci4ApiCore\Http\Filters\CorrelationIdFilter::class,
        'maintenance' => \dcardenasl\Ci4ApiCore\Http\Filters\MaintenanceFilter::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            // 'forcehttps', // Force Global Secure Requests - Enabled conditionally in constructor
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            'maintenance', // 503 short-circuit when MAINTENANCE_MODE=true (audit B10.4); /health etc. bypass internally.
            'correlationid', // Resolve / generate X-Request-ID and stamp on RequestIdHolder (audit B10.1)
            'locale', // Set locale from Accept-Language header
            'cors', // Handle CORS preflight (OPTIONS) requests
            'invalidchars', // Filter invalid/malicious characters from requests
            // 'honeypot',
            // 'csrf',
        ],
        'after' => [
            'cors', // Add CORS headers to all responses
            // 'honeypot',
            'secureheaders', // Add security headers to all responses
            'deprecationheaders', // Emit Deprecation/Sunset/Link headers for deprecated API versions (audit B7.2)
            'correlationid', // Echo X-Request-ID on every response (audit B10.1)
            'requestLogging' => ['except' => ['health', 'ping', 'ready', 'live']], // Skip noisy health probes
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
