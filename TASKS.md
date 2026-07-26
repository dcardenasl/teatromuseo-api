# TASKS — ci4-website-builder-api

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

*(vacío)*

## ⚪ Backlog

- [ ] **API-012 — Docker out-of-the-box:** validar la orquestación cross-repo en `ci4-kickstart`
  después de la idempotencia de `docker/entrypoint.sh`.

## ⚠️ Señales de activación

- **API-014 — Multi-tenant nativo:** fuera de alcance mientras no exista una señal real que exija
  aislamiento físico o un SLA propio.
- **SEÑAL-API-001 — `InvalidChars` con enteros en JSON:** mantener el workaround documentado hasta
  que exista un segundo endpoint afectado o una corrección upstream de CI4.
- **FILES-001 — Endpoints de archivos faltantes:** crear tareas individuales cuando se prioricen
  `PATCH /files/{id}`, replace, regeneración de variantes o consulta de usages.

## 🏗️ Contratos de arquitectura

- **DTO-First:** toda entrada y salida de Controller usa DTOs; no arrays raw sin contrato.
- **Services puros:** no conocen HTTP ni `$request`.
- **Controllers delgados:** usar `handleRequest()` de `ApiController`.
- **Permisos:** usar separador `.`; nunca `:`.
- **Rutas:** organizar endpoints en `app/Config/Routes/v1/<dominio>.php`.
- **Tests:** todo endpoint nuevo necesita al menos un Feature test.
- **CRUD nuevo:** preferir `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
- **OpenAPI:** regenerar Swagger al cerrar cambios de endpoints.
- **Migraciones:** nunca modificar migraciones existentes; crear una nueva para cada cambio de schema.

## 🔧 Referencias

- Histórico: [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md)
- Tracker global: [`../TASKS.md`](../TASKS.md)
