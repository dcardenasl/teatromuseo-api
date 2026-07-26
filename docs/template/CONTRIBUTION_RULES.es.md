# Reglas de Contribución (Template)

Estas reglas definen el estándar mínimo de contribución para este repositorio y para proyectos derivados que usen este template.

## 1. No negociables de arquitectura

1. Controllers delgados (`ApiController`, `resolveDefaultService()`, `handleRequest()`).
2. Services con lógica de negocio pura; sin manejo HTTP.
3. Transferencia entre capas con DTOs (`readonly`, request DTOs validados).
4. Clases runtime (`Commands`, `Filters`) resuelven dependencias por contenedor/helpers (`Services::*`, `model()`).
5. Mensajes de validación y excepciones deben usar claves `lang()` (sin textos hardcodeados visibles al usuario).
6. Para módulos CRUD nuevos, seguir `docs/template/CRUD_FROM_ZERO.es.md` como flujo canónico.

## 2. Entregables obligatorios por cambio

1. Cambios alineados con `docs/template/ARCHITECTURE_CONTRACT.md`.
2. Paridad de idioma en `app/Language/en` y `app/Language/es` cuando aplique.
3. Paridad EN/ES en documentación nueva o modificada dentro de `docs/`.
4. Tests actualizados en la suite correspondiente (`Unit`, `Feature`, `Integration`).
5. Ubicación documental alineada con `docs/DOCUMENTATION_SCOPE.md` (sin duplicación entre secciones).

## 3. Quality gates antes de merge

1. `composer cs-check`
2. `composer phpstan`
3. `composer arch-drift`
4. `php scripts/i18n-check.php`
5. `php scripts/docs-i18n-parity-check.php`
6. `vendor/bin/phpunit`
7. Recomendado: `composer quality` para ejecutar toda la cadena.

Consulta `docs/architecture/DRIFT_GUIDE.es.md` siempre que `composer arch-drift` u otros guardrails de arquitectura fallen para saber cómo diagnosticar y corregir el incidente.

## 4. Checklist de aceptación de PR

1. Alcance e impacto documentados explícitamente.
2. Impacto de contratos internos/externos declarado.
3. Acciones de migración documentadas si hubo cambios de contrato.
4. No introducir TODOs sin resolver ni atajos temporales.
5. Declarar explícitamente impacto de architecture drift cuando se tocan controllers/services/filters.
6. Consultar `docs/architecture/DRIFT_GUIDE.es.md` cuando fallen los guardrails de arquitectura.
7. Si se agregó un CRUD nuevo, confirmar que se siguió el flujo scaffold + migración + module check.
