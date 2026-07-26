# Filters (Middleware)


## Filter Pipeline

Global pipeline (configured in `app/Config/Filters.php`):

```
LocaleFilter → CorsFilter → InvalidChars → Controller → CorsFilter → SecurityHeadersFilter → RequestLoggingFilter
```

Route-level pipeline (configured in `app/Config/Routes.php`) for authenticated admin endpoints:

```
AuthThrottleFilter/JwtAuthFilter → RoleAuthorizationFilter → Controller
```

## Available Filters

| Filter | Alias | Purpose | Configuration |
|--------|-------|---------|---------------|
| `CorsFilter` | `cors` | Handle CORS and preflight | `.env` CORS_* |
| `ThrottleFilter` | `throttle` | Generic rate limiting (available alias) | `RATE_LIMIT_*` |
| `AuthThrottleFilter` | `authThrottle` | Stricter throttling for auth/public identity endpoints | `AUTH_RATE_LIMIT_*` |
| `JwtAuthFilter` | `jwtauth` | Validate JWT token | `JWT_SECRET_KEY` |
| `RoleAuthorizationFilter` | `roleauth` | Check user role | Route arg: `roleauth:admin` |
| `RequestLoggingFilter` | `requestLogging` | Persist request telemetry (except health probes) | `REQUEST_LOGGING_ENABLED` |
| `SecurityHeadersFilter` | `secureheaders` | Adds secure response headers | Config-driven |
| `FeatureToggleFilter` | `featureToggle` | Blocks disabled feature routes with 503 | `MONITORING_ENABLED`, `METRICS_ENABLED` |

## Usage in Routes

```php
// app/Config/Routes.php

// Public auth endpoints with auth-specific throttling
$routes->group('', ['filter' => 'authThrottle'], function ($routes) {
    $routes->post('auth/login', 'AuthController::login');
});

// Protected (JWT required)
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('users', 'UserController::index');
    
    // Admin only
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('users', 'UserController::create');
    });
});
```

## Creating Custom Filters

```php
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Logic before controller
        // Return response to stop pipeline, or null to continue
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Logic after controller
        // Return modified response
    }
}

// Register in app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];
```
