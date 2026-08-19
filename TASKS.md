# TASKS — teatromuseo-api (Hub)

> Trabajo abierto del Hub. Seguimiento cross-repo:
> [`../TASKS.md`](../TASKS.md). Cierres históricos:
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

- [x] **FILES-READ-02 — Snapshot de usos cross-domain.** Cerrada 2026-08-19.
  El Hub expone `usage-snapshot` con una autorización de archivo, estado por
  dominio y contexto CMS preservado; el BFF ya no repite la consulta CMS para
  reconstruir la misma información. Verificado con endpoint, Swagger, PHPStan,
  `composer quality` y la suite completa.

- [x] **CMS-EDITOR-01 — Bundle IAM editorial cross-app.** Cerrada 2026-08-18.
  `cms-editor` ahora incluye edición de colecciones, formularios,
  submissions y traducciones de archivos, además de `cms.languages.read`;
  `cms-admin` quedó completo para esas operaciones. Los perfiles CMS se
  componen automáticamente con `user` para conservar `self.access` y
  `files.read/write`. Verificado con `composer quality` (787 tests, 2.270
  asserts, 2 skips), `domain:sync-permissions` y `CmsRolesSeeder` local.

- [x] **FILES-READ-01 — Biblioteca de medios compartida cross-owner** — cerrada
  2026-08-18. `FILE_USER_SCOPED_FILES=false` queda como la única configuración
  de scope para que `files.read` liste, consulte, descargue y use el picker con
  archivos de cualquier usuario. Se añadieron regresiones de lectura cross-owner
  sin abrir `files.write`/`files.admin` para mutaciones; quality verde.

- [x] **SEC-01 — Control de acceso en gestión de archivos** — cerrada
  2026-08-12. Se eliminó el bypass de ownership por booleano, se centralizó la
  política por acción, se añadió `files.admin`, filtros de ruta, migración
  idempotente y regresiones cross-owner; `composer quality` quedó verde.

- [x] **ADM-DASH-01 — Resumen agregado del dashboard administrativo** — cerrada
  2026-08-11. Lectura autenticada y permission-aware para datos propiedad del
  Hub, con contrato, permisos y pruebas de regresión.

- [x] **SEC-02 — Ciclo de vida de tokens (hallazgo 1.4)** — cerrada
  2026-08-12. Rotación por familias con detección de reutilización, revocación
  global inmediata mediante `auth_token_version`, caché negativo eliminado,
  auditoría crítica y regresiones completas.

## 🟡 Próximo

### BFF de lectura directa (2026-08-13) — ver `../docs/plan/2026-08-13-plan-bff-completo.md`

`teatromuseo-bff` va a leer directo, solo-lectura, la tabla `files` (y
`file_references`) de este Hub, para resolver metadata de media sin pasar por
HTTP (`HubClient::resolvePublicFileMeta()`). El resto del Hub no se toca —
esta es la única tabla involucrada.

- [ ] **API-PR-01 — Usuario MySQL `hub_readonly` acotado.** Crear un usuario
  MySQL `SELECT`-only con grants limitados a `files`/`file_references`
  únicamente (no al resto del esquema del Hub), en dev y producción.
  Documentar en el README de infraestructura del Hub. Confirmar antes que
  `teatromuseo-cms-domain`, `teatromuseo-catalog-domain`,
  `teatromuseo-event-domain` y este Hub comparten el mismo servidor MySQL en
  producción (fuerte evidencia en dev: los 4 apuntan a `127.0.0.1:3306`) — si
  no, coordinar accesibilidad de red/firewall primero. Bloquea `BFF-DB-04`/
  `BFF-DB-05` en `teatromuseo-bff/TASKS.md`.

  **Pendiente externo:** el BFF ya consume el grupo `hub_readonly`, pero la
  creación del usuario, grants y validación de red requiere acceso al entorno
  MySQL y credenciales de infraestructura.

### Saneamiento arquitectónico heredado (prioridad 2)

- [ ] **CFG-08** — Actualizar `ci4-api-scaffolding` y retirar el paso muerto
  `scripts/ci-strip-local-repos.php`.
- [ ] **CORE-02 residual** — Evaluar `AppExceptionHandler`, `AuditRepository`,
  `MetricModel`, `RequestLogModel`, `AuditLogModel` y el drift de migraciones de
  infraestructura; no asumir que `core:install` lo resuelve.
- [ ] **CORE-06** — Unificar códigos de permisos con una migración explícita de
  `permissions`/`role_permissions` y ventana de mantenimiento. No ejecutar solo
  `domain:sync-permissions`.
- [ ] **MIG-02** — Revisar las cadenas de migraciones de membresías/roles cuyo
  neto es cero y decidir si se consolidan o se documentan.
- [ ] **MIG-03** — Sacar `UsersLoadTestSeeder` del directorio de seeds de
  producción o convertirlo en fixture de tests.
- [ ] **DEAD-02** — Reemplazar el `public/ping` de plantilla por la superficie
  pública real del Hub, cuando exista un consumidor definido.
- [ ] **API-012** — Validar la orquestación Docker cross-repo de `ci4-kickstart`.

### Dependencias y conflictos

- El Hub no debe cambiar permisos, auth o contratos durante `QA-01..04` ni el
  cutover público sin coordinar con CMS, Catalog y Events.
- `CORE-06` es el único pendiente explícitamente bloqueado por una ventana de
  mantenimiento y migración de datos; conservar esa protección.
- `MIG-02/03` pueden ejecutarse después de la QA pública si no alteran los
  contratos consumidos por Web.

## 🏗️ Contratos de arquitectura

- El Hub es la única aplicación que emite JWT.
- Controllers DTO-first y servicios sin acceso SQL crudo cuando exista un Model
  apropiado.
- Los endpoints públicos deben usar `webappkey`; no usar JWT de usuario para
  tráfico público.
