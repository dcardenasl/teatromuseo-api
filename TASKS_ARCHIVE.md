# TASKS_ARCHIVE — ci4-api-starter

> Historial de tareas completadas. Movido desde TASKS.md para mantener el tracker activo liviano.
> Última actualización: 2026-08-20

---

## ✅ Cierres 2026-08-11..19 — archivados 2026-08-20

### FILES-READ-02 — Snapshot de usos cross-domain

Cerrada 2026-08-19. El Hub expone `usage-snapshot` con una autorización de
archivo, estado por dominio y contexto CMS preservado; el BFF ya no repite la
consulta CMS para reconstruir la misma información. Verificado con endpoint,
Swagger, PHPStan, `composer quality` y la suite completa.

### CMS-EDITOR-01 — Bundle IAM editorial cross-app

Cerrada 2026-08-18. `cms-editor` ahora incluye edición de colecciones,
formularios, submissions y traducciones de archivos, además de
`cms.languages.read`; `cms-admin` quedó completo para esas operaciones. Los
perfiles CMS se componen automáticamente con `user` para conservar
`self.access` y `files.read/write`. Verificado con `composer quality` (787
tests, 2.270 asserts, 2 skips), `domain:sync-permissions` y `CmsRolesSeeder`
local.

### FILES-READ-01 — Biblioteca de medios compartida cross-owner

Cerrada 2026-08-18. `FILE_USER_SCOPED_FILES=false` queda como la única
configuración de scope para que `files.read` liste, consulte, descargue y use
el picker con archivos de cualquier usuario. Se añadieron regresiones de
lectura cross-owner sin abrir `files.write`/`files.admin` para mutaciones;
quality verde.

### SEC-01 — Control de acceso en gestión de archivos

Cerrada 2026-08-12. Se eliminó el bypass de ownership por booleano, se
centralizó la política por acción, se añadió `files.admin`, filtros de ruta,
migración idempotente y regresiones cross-owner; `composer quality` quedó
verde.

### SEC-02 — Ciclo de vida de tokens (hallazgo 1.4)

Cerrada 2026-08-12. Rotación por familias con detección de reutilización,
revocación global inmediata mediante `auth_token_version`, caché negativo
eliminado, auditoría crítica y regresiones completas.

### ADM-DASH-01 — Resumen agregado del dashboard administrativo

Cerrada 2026-08-11. Lectura autenticada y permission-aware para datos
propiedad del Hub, con contrato, permisos y pruebas de regresión.

---

## ✅ Refactorización y hardening (2026-05-26)

| ID | Descripción | Estado |
|---|---|---|
| API-017 | Auth/IAM DTO typing. `AuthService` y `AuthServiceInterface` ahora exponen `LoginRequestDTO`, `RegisterRequestDTO`, `UpdateMeRequestDTO` y retornan `LoginResponseDTO`, `RegisterResponseDTO`, `MeResponseDTO` concretos. `SessionManager::generateSessionResponse()` devuelve `LoginResponseDTO` con `MeResponseDTO` anidado. `UserPermissionsService` deja de ensamblar `ApplicationSummary` como array y retorna el DTO directamente. `AuthServiceTest` y un test unitario nuevo para `LoginResponseDTO` cubren el contrato. Verificado con `composer quality`. | ✅ |
| AUDIT-001 | Audit Trail Reliability. `GET /health` degrada a `degraded` cuando el único problema es presión crítica de disco y la auditoría asíncrona está activa; se mantiene `unhealthy` para otros fallos. Añadido test unitario para la política de degradación. Verificado con `composer quality`. | ✅ |

## ✅ CORE v1.0 milestone — paquete consumido desde Packagist (2026-05-09)

| ID | Descripción | Estado |
|---|---|---|
| API-011 | Publicar `ci4-api-core` en Packagist + migrar de path repo a constraint Packagist. Cerrado por CORE-006 cross-repo: `dcardenasl/ci4-api-core` v0.4.0 publicado 2026-05-09, `composer.json` del api-starter ya consume desde Packagist. | ✅ |

---

## ✅ Enterprise hardening (Milestone B5–B11, 2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| B7.1 | `AssignableRolesService` extracción del controller anti-pattern | ✅ |
| B7.2 | Headers de deprecación + `/api/versions` + ADR-008 (EN+ES) | ✅ |
| B7.3 | `Idempotency-Key` opt-in + migración `idempotency_keys` + ADR-009 (EN+ES) | ✅ |
| B7.4 | RFC 7807 Problem Details opt-in + ADR-010 (EN+ES) | ✅ |
| B7.5 | Convención de paginación documentada en `docs/tech/pagination.md` (EN+ES) | ✅ |
| B9.2 | `GoogleLoginSoftDeletedUserTest` (2 tests, contrato de reactivación) | ✅ |
| B10.1 | `CorrelationIdFilter` + `RequestIdHolder` + propagación en ApiClient | ✅ |
| B11.1 | ADR-011 (multi-tenancy out-of-scope) + ADR-012 (config runtime mutability) EN+ES | ✅ |
| B11.2 | 4 runbooks (rotate JWT, failed migration, upgrade CI4, token-leak incident) EN+ES | ✅ |

---

## ✅ Endpoints de integración hub↔domain (2026-05-06/07)

| ID | Descripción | Estado |
|---|---|---|
| API-001 | `POST /api/v1/auth/introspect` — introspección JWT (RFC 7662-style). Filter `appKeyRequired`, `TokenIntrospectionService`, DTOs, doc OpenAPI. 8 feature tests. 603 tests verdes. | ✅ |
| API-002 | `POST /api/v1/auth/service-token` — M2M auth sin usuario. `ServiceTokenService`, `ApplicationPermissionsResolver`, `JwtService::encodeServiceToken()`, TTL configurable. 6 feature + 5 integration tests. 614 tests verdes. | ✅ |
| API-003 | Reglas de modificación de usuarios: `PATCH /api/v1/auth/me` (allowlist first_name/last_name/avatar_url). Email inmutable salvo superadmin. `assertNotSelf()` en PUT. | ✅ |
| API-005 | Bug: `JwtAuthFilter` crasheaba con service tokens (uid undefined). Null-safe + `PermissionFilter` distingue 401 vs 403. | ✅ |
| API-006 | `/auth/introspect` re-resuelve scope según `X-App-Key` del caller. `EffectivePermissionsResolver(uid, application_id)`. | ✅ |
| API-007 | `apps:bootstrap --create-api-key`: genera API key activa, output parseable `API_KEY=apk_...`. 4 integration tests. Desbloquea KICK-001. | ✅ |

---

## ✅ Deudas post-port + consumo ci4-api-core v0.2.0 (2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| API-015 | `HandlesTranslations` cascade delete — `afterDelete()` llama `TranslationModel::deleteForEntity()` dentro de la transacción de `BaseCrudService::destroy`. Integration test store/update/delete completo. | ✅ |
| API-016 | `GalleryService` → `PivotRepositoryInterface`. `PivotRepository` abstract, `findByIds` en `FileRepository`. Integration test end-to-end con fixture pivot table. Whitelist de `ServiceModelDependencyConventionsTest` reducida. | ✅ |
| API-017 | `DataBag` eliminado. `ResponseMapper::map()` acepta `object\|array` directamente (CORE-009). `HandlesTranslations::mapToResponse` pasa array directo. | ✅ |

**Refactor de consumo** (sin ID de tarea — trabajo derivado de ci4-api-core v0.2.0):
- Helpers procedurales consumidos desde `dcardenasl/ci4-api-core`
- Cadena de audit consumida desde core
- HTTP filters y logging stack consumidos desde core
- Mappers y utilidades de support consumidas desde core
- `BaseRepository` consumido desde core
- Exception handlers HTTP consumidos desde core
- `Filterable`, `Searchable`, `QueryBuilder` consumidos desde core
- Fixtures de tests actualizados a imports de `dcardenasl/ci4-api-core`
- `composer.lock` + swagger regenerados

---

*TASKS_ARCHIVE · ci4-api-starter · 2026-05-07*

---

## 📦 Migrado desde `TASKS.md` — 2026-07-21

- **PHPSTAN-01..08** — ampliación de paths, reducción del baseline a cero, correcciones de
  false-safety, anotaciones de tipos, generics, eliminación de dead code y suites unit/feature en
  verde.
- **IAM-001..003** — inferencia automática de `application_id`, auditoría de modelos y
  cumplimiento de `BaseAuditableModel`.
- **DTO-001..002** — auditoría de Services que usaban arrays y guardrail de análisis estático para
  evitar regresiones DTO-first.
- **CORE-001..004** — hardening de `RepositoryInterface` y `AuditServiceInterface`, boundary tipado
  de `ApiController`, implementación estricta en el starter y plantillas tipadas del scaffolder.

La orquestación Docker cross-repo permanece abierta como **API-012** en el tracker activo; no se
considera cerrada por el hecho de que el entrypoint ya sea idempotente.

---

## ✅ Saneamiento 2026-08-05..07 — cierres reconciliados

Se archivaron las tareas cerradas que seguían visibles en `TASKS.md`: `CFG-02`,
`CFG-05`, `DOC-01`, `DATA-01`, `LAYER-02`, `LAYER-03` y `LAYER-04`, junto con
los cierres de migración/higiene verificados en la auditoría cross-repo. Los
residuos explícitos (`CFG-08`, `CORE-02`, `CORE-06`, `MIG-02/03`, `DEAD-02` y
`API-012`) permanecen activos.

La evidencia completa está en [`../docs/plan/2026-08-05-saneamiento-arquitectonico.md`](../docs/plan/2026-08-05-saneamiento-arquitectonico.md).
