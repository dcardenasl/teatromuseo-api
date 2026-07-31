# TASKS — ci4-website-builder-api

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

- **DEPS-001 — Revisar paquetes dev abandonados (`composer audit`):** `sebastian/code-unit` y
  `sebastian/code-unit-reverse-lookup` (transitivos de PHPUnit) aparecen marcados como
  "abandoned" sin reemplazo sugerido por Packagist. Sin CVE asociado — no es urgente, pero
  conviene revisar si una actualización de PHPUnit los elimina de la dependency tree, o si hay
  que vivir con ellos indefinidamente. Detectado 2026-07-31 vía `composer audit` durante una
  auditoría de seguridad transversal del monorepo.

## ✅ Completadas

- **NULL-CLEAR-001 — Fix "no se puede limpiar un campo nullable vía update" en toda la familia *UpdateRequestDTO (2026-07-30):**
  Bug arquitectónico transversal encontrado durante FILE-GUARD-001: todo Update DTO generado por el
  scaffolding usa `array_filter($v !== null)` en `toArray()` + `isset()`/`??` en `map()`, lo que
  descarta CUALQUIER campo enviado como `null` antes de llegar al Model — hacía imposible limpiar
  explícitamente un campo nullable de la BD (ej. "Quitar" un cover_file_id nunca funcionó, para
  ningún recurso). Corregido en los 4 DTOs propios del Hub que tenían columnas nullable reales:
  `PermissionUpdateRequestDTO` (description), `RoleUpdateRequestDTO` (description),
  `UserUpdateRequestDTO` (first_name/last_name/avatar_url). `ApiKeyUpdateRequestDTO` quedó sin
  tocar — ninguno de sus campos editables es nullable en `api_keys`, no había bug que arreglar ahí.
  Patrón aplicado: ternario de una sola línea por propiedad (nunca if/else — PHPStan nivel 8 marca
  `property.readOnlyAssignNotInConstructor` como falso positivo cuando hay dos sentencias de
  asignación a la misma propiedad readonly, aunque sean ramas mutuamente excluyentes) +
  `array_key_exists()` para distinguir "campo omitido" de "campo enviado como null" + acumulador
  `$mappedFields` que `toArray()` retorna directo. Columnas NOT NULL nunca aceptan null explícito
  (se tratan igual que omitidas, respetando el constraint de BD) — decisión campo por campo basada
  en el schema real (`DESCRIBE`), no un blanket fix.
  **`UserUpdateRequestDTO` requirió tocar también `UpdateUserAction::buildUpdateData()`** — ese
  action NUNCA llamaba `toArray()`, leía las propiedades del DTO directamente con sus propios
  checks `!== null` (mismo bug, duplicado). `password` quedó **deliberadamente excluido** del
  mecanismo de clearing genérico (excluido de `toArray()`/`mappedFields` por completo, igual que
  `role_ids`) — nunca debe ser posible limpiar la contraseña de un usuario a NULL vía este PATCH
  genérico; sigue el mismo trato especial de siempre (`$request->password !== null` → hash y
  escribe, si no se toca).
  Verificado end-to-end real (no solo tests): `PUT /iam/permissions/1` con body `{"description":
  null}` → 200, `SELECT` directo confirma `description IS NULL` en BD; payload que SOLO limpia un
  campo a null ya no dispara `noFieldsToUpdate` (el array ya no queda vacío tras el fix).
  `composer quality` ✅ (676/676 tests, PHPStan nivel 8 sin errores incluyendo `app/DTO`).
  **Hallazgo secundario, no corregido**: la respuesta de `PUT /iam/permissions/1` tras limpiar
  `description` la muestra como `""` en vez de `null` en el JSON — la escritura en BD es un NULL
  real (confirmado por SQL), es solo el `ResponseDTO`/mapper que castea a string vacío al serializar.
  Cosmético, no afecta integridad de datos; no se tocó (fuera de alcance de este fix).
  **Fuera de alcance, señalado pero no corregido**: el generador de scaffolding
  (`dcardenasl/ci4-api-scaffolding`, dependencia real vía Composer, no un path-repo editable en este
  workspace) sigue emitiendo el patrón `array_filter` roto en cualquier `make-crud.sh` nuevo — el fix
  real requiere una versión parcheada del paquete, no algo que se pueda arreglar editando `vendor/`.

- **FILE-GUARD-001 — Delete guard + invalidación de caché cross-domain para archivos (2026-07-30):**
  `FileService::destroy()`/`forceDestroy()` solo veían usages dentro del propio Hub
  (`file_references`), ciegos a `cover_file_id`/`gallery_file_ids` en catalog-domain/
  event-domain y a `cms_file_references` en cms-domain — un archivo en uso por un evento o
  pieza de colección se podía borrar sin aviso. Y `HubClient::invalidateFileMetaCache()`
  existía en los 3 domains pero nadie lo llamaba nunca. Ambos gaps cerrados:
  - Nuevo `App\Libraries\Domains\DomainFileUsageClient` (`DomainFileUsageClientInterface`),
    HMAC-firmado (`Config\DomainWebhooks`, env `HUB_INTERNAL_SECRET` + `{CMS,CATALOG,EVENT}_DOMAIN_URL`),
    fail-open por diseño (un domain caído no bloquea operaciones de archivo en el Hub, solo
    se loguea).
  - `FileService::collectAllUsages()` combina `fileReferenceRepository->getByFileId()` +
    `domainFileUsageClient->collectUsages()`; usado en `getUsages()` (ahora la vista "usages"
    del admin también ve cross-domain) y en el guard de `destroy()`/`forceDestroy()`.
  - `replace()`/`destroy()`/`forceDestroy()` disparan `broadcastInvalidate()` tras éxito
    (best-effort, nunca revierte la operación si un domain no responde).
  - Verificado end-to-end real (no solo tests): subida de archivo → asignado como
    `cover_file_id` de un evento real → `DELETE /files/{id}` devuelve 409 correctamente →
    `replace()` del archivo se refleja sin demora en `/api/v1/public/events/{id}` del
    event-domain (antes tardaría hasta 300s de TTL de caché) → limpieza de datos de prueba.
  - `composer quality` ✅ en los 4 repos (api + cms-domain + catalog-domain + event-domain).
  - **Hallazgo colateral, no corregido (fuera de alcance):** `EventUpdateRequestDTO::toArray()`
    y `CollectionItemUpdateRequestDTO::toArray()` usan `array_filter($v !== null)`, así que
    **ningún campo nullable puede limpiarse explícitamente vía update** (enviar
    `cover_file_id: null` se descarta en silencio en vez de hacer `NULL` en BD) — el botón
    "Quitar" del picker de imagen de portada en el admin nunca ha funcionado, para ningún
    recurso. Es un patrón preexistente en todo el DTO de update, no algo introducido aquí.

- **LEGACY-001 — Motor de migración de datos legacy (dry-run):** `app/Commands/LegacyDryRun.php`,
  `app/Libraries/LegacyMigration/` (lector de dumps SQL, resolutor de assets con guardas
  anti path-traversal, catálogo y analizador de slice A) + tablas de control
  (`2026-07-27-024000_CreateLegacyMigrationControlTables.php`) y su suite de tests unit/integration.
  Opera únicamente en modo `dry_run`; no ejecuta escrituras destructivas contra la BD legacy.
  Pendiente como próximo paso: habilitar el modo de escritura real detrás de un flag explícito
  cuando se decida ejecutar la migración productiva.

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
