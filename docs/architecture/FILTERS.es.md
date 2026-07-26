# Filters (Middleware)


## Pipeline de Filtros

Pipeline global (configurado en `app/Config/Filters.php`):

```
LocaleFilter → CorsFilter → InvalidChars → Controller → CorsFilter → SecurityHeadersFilter → RequestLoggingFilter
```

Pipeline por ruta (configurado en `app/Config/Routes.php`) para endpoints administrativos autenticados:

```
AuthThrottleFilter/JwtAuthFilter → RoleAuthorizationFilter → Controller
```

## Filtros Disponibles

| Filter | Alias | Propósito | Configuración |
|--------|-------|---------|---------------|
| `CorsFilter` | `cors` | Manejar CORS y preflight | `.env` CORS_* |
| `ThrottleFilter` | `throttle` | Rate limiting genérico (alias disponible) | `RATE_LIMIT_*` |
| `AuthThrottleFilter` | `authThrottle` | Límite más estricto para endpoints públicos de autenticación/identidad | `AUTH_RATE_LIMIT_*` |
| `JwtAuthFilter` | `jwtauth` | Validar token JWT | `JWT_SECRET_KEY` |
| `RoleAuthorizationFilter` | `roleauth` | Verificar rol de usuario | Arg de ruta: `roleauth:admin` |
| `RequestLoggingFilter` | `requestLogging` | Persistencia de telemetría HTTP (excepto health probes) | `REQUEST_LOGGING_ENABLED` |
| `SecurityHeadersFilter` | `secureheaders` | Añade cabeceras seguras de respuesta | Config-driven |
| `FeatureToggleFilter` | `featureToggle` | Bloquea rutas de features deshabilitadas con 503 | `MONITORING_ENABLED`, `METRICS_ENABLED` |

## Uso en Rutas

```php
// app/Config/Routes.php

// Público auth con throttling específico de autenticación
$routes->group('', ['filter' => 'authThrottle'], function ($routes) {
    $routes->post('auth/login', 'AuthController::login');
});

// Protegido (JWT requerido)
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('users', 'UserController::index');

    // Solo admin
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('users', 'UserController::create');
    });
});
```

## Crear Filtros Personalizados

```php
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Lógica antes del controller
        // Retornar respuesta para detener pipeline, o null para continuar
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Lógica después del controller
        // Retornar respuesta modificada
    }
}

// Registrar en app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];
```
