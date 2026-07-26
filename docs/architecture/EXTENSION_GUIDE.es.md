# Guía de Extensión


## Añadir un Nuevo Recurso CRUD

Proceso completo paso a paso:

1. **Scaffold primero** - `bash bin/make-crud.sh Product Catalog 'name:string:required|searchable,price:decimal:required' yes products`
2. **Validar scaffold** - `php spark module:check Product --domain Catalog`
3. **Ejecutar migración(es)** - `php spark migrate` (generada por scaffold)
4. **Reiniciar servidor** - `pkill -f 'spark serve'; php spark serve --port 8080 &` (para que se carguen las rutas nuevas)
5. **Alinear entity/model** - campos, casts, validación, traits de query
6. **Cerrar contratos DTO** - Request/Response DTOs + atributos OpenAPI
7. **Cerrar servicio** - lógica pura + estrategia de repositorio
8. **Registrar dependencias** - actualizar `app/Config/Services.php` cuando aplique
9. **Crear/verificar rutas** - actualizar `app/Config/Routes.php`
10. **Añadir archivos de idioma** - `app/Language/{lang}/Products.php`
11. **Escribir tests** - pruebas Unit, Integration, Feature
12. **Ejecutar quality/docs gates** - `composer quality` + `php spark swagger:generate`

> Para entornos interactivos puedes usar `php spark make:crud Product --domain Catalog` y el motor te consultará cada campo. En entornos no-TTY el wrapper es obligatorio — el shell puede consumir los pipes en `--fields` y el motor queda esperando entrada interactiva.

## Inicio Rápido

Ver [`../../GETTING_STARTED.md`](../../GETTING_STARTED.md) para un recorrido completo con ejemplos de código.

Como referencia mantenida y cercana a producción, revisa el recurso `Files` bajo `app/DTO/*/Files`, `app/Controllers/Api/V1/Files`, `app/Services/Files` y `tests`. Usa `php spark module:check <Resource> --domain <Domain>` para validar tus propios módulos.

El comando `make:crud` genera archivos de migración, entity/model/interface/service/controller/DTOs/docs/i18n/tests, utilizando un esquema único para asegurar la sincronización en todas las capas.

## Añadir Filtros Personalizados

```php
// 1. Crear filtro
// app/Filters/MyFilter.php
class MyFilter implements FilterInterface { ... }

// 2. Registrar alias
// app/Config/Filters.php
public array $aliases = [
    'myfilter' => \App\Filters\MyFilter::class,
];

// 3. Usar en rutas
$routes->group('', ['filter' => 'myfilter'], function ($routes) {
    // ...
});
```

## Añadir Excepciones Personalizadas

```php
// app/Exceptions/PaymentRequiredException.php
class PaymentRequiredException extends ApiException
{
    protected int $statusCode = 402;
}
```
