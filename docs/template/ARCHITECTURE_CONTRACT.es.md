# Contrato de Arquitectura del Template

Reglas obligatorias para cualquier módulo construido sobre este template.

## Reglas de capas

1. Controllers extienden `ApiController`.
2. Controllers usan `handleRequest(...)` con Request DTO.
3. Services contienen lógica de negocio (sin construcción de respuestas HTTP).
4. Lecturas retornan DTOs.
5. Flujos de comando retornan `OperationResult`.

## Reglas DTO

1. Request DTOs extienden `BaseRequestDTO`.
2. Validación en `rules()/messages()` durante construcción.
3. Response DTOs implementan `DataTransferObjectInterface`.

## Contrato CRUD base

1. `index()` retorna DTO paginado (`PaginatedResponseDTO`).
2. `show()/store()/update()` retornan DTO de recurso.
3. `destroy()` retorna `bool`.

## Operación

1. Registrar servicios en `app/Config/Services.php`.
2. Mantener i18n en `en` y `es`.
3. Mantener pruebas Unit/Feature/Integration según alcance.
4. `make:crud` es el bootstrap recomendado por defecto, seguido de creación explícita de migraciones.
5. La persistencia CRUD estándar usa `GenericRepository`; repositorios dedicados se crean cuando hay queries de dominio no triviales.
6. En clases runtime (`Commands`, `Filters`) resolver dependencias con `Config\Services`/helpers de modelo (`model()`), evitando `new *Model()` directo y `service('...')`.
7. `composer quality` debe pasar antes de merge.
