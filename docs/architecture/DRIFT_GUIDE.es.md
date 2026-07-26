# Guía de Desvío de Arquitectura

## Propósito
Explicar cómo interpretar y corregir fallos reportados por `composer arch-drift` o los guardrails de CI.

## Guardrails cubiertos
- Pipeline de controladores (`ApiController`, DTOs, cobertura de rutas).
- Pureza de servicios (`ServicePurityConventionsTest`, evita `env()`/`Config\Services` dentro de `app/Services`).
- Feature toggles (`FeatureToggleFilter`, `FeatureFlags` config).
- Contratos CRUD y `OperationResult`.

## Cómo ejecutar la revisión
1. Ejecuta `composer arch-drift`.
2. El script corre los tests de arquitectura más los checks de i18n/docs.
3. Si falla, el output indica el test y la causa (archivo y mensaje).

## Fallos comunes y soluciones
- **Snippet de DTO faltante**: usa `handleRequest(..., RequestDTO::class)` en el controller en lugar de parsear manualmente.
- **Violación de pureza de servicio**: mueve los accesos a `env()`/`Config\Services` a `Config/Services` e inyecta valores en el constructor.
- **Cobertura de rutas faltante**: registra el controller en `app/Config/Routes.php`.
- **Feature toggle identificado**: aplica `featureToggle:...` y verifica que `Config/FeatureFlags` exponga ese toggle.

## Acción y seguimiento
1. Agrega tests (unitarios/feature) que cubran el caso corregido.
2. Actualiza `docs/architecture/README.md` o el ADR correspondiente si la decisión de arquitectura cambió.
3. Referencia esta guía en los PRs donde se toquen controllers/services/filters para demostrar compliance.
