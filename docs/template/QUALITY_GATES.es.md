# Quality Gates del Template

Checklist de calidad obligatoria para cada PR.

## 1. Estático y estilo

1. `composer cs-check`
2. `composer phpstan`
3. `composer arch-drift`
4. `php scripts/i18n-check.php`
5. `php scripts/docs-i18n-parity-check.php`

## 2. Pruebas

1. `php spark tests:prepare-db`
2. `vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox`
3. Recomendado: `composer quality`

## 3. Gate de arquitectura

Deben pasar los tests de `tests/Unit/Architecture/`:

1. convenciones de `ApiController`
2. convenciones de controladores
3. contratos `OperationResult`
4. contratos DTO paginados para CRUD
5. uso de DTOs en pipeline de controladores
6. convenciones de instanciación runtime (sin `new *Model()` en Commands/Filters)
7. convenciones de Filters (`strict_types` y uso de `Config\Services`)
8. cobertura de rutas para controllers API v1
9. pureza de servicios (sin `Config\Services`, `env()` ni `getenv()` en `app/Services`)

## 4. Contratos mínimos

1. Controllers con `handleRequest(...)` + DTOs.
2. Services sin respuestas HTTP.
3. `CrudServiceContract::index()` retorna `DataTransferObjectInterface`.
4. Comandos retornan `OperationResult`.
5. Paridad i18n (`en` + `es`) para nuevas claves.

## 5. Checklist de release

1. `git status` limpio después de tests.
2. Documentación actualizada cuando cambian contratos o convenciones.
3. El scaffold de módulos nuevos genera archivos compatibles con guardrails de contrato.
4. Los CRUD nuevos siguen `docs/template/CRUD_FROM_ZERO.es.md` (incluyendo validación de scaffold y paso explícito de migración).
