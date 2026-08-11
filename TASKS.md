# TASKS — teatromuseo-api (Hub)

> Trabajo abierto del Hub. Seguimiento cross-repo:
> [`../TASKS.md`](../TASKS.md). Cierres históricos:
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

- [x] **ADM-DASH-01 — Resumen agregado del dashboard administrativo** — cerrada
  2026-08-11. Lectura autenticada y permission-aware para datos propiedad del
  Hub, con contrato, permisos y pruebas de regresión.

## 🔴 En progreso

## 🟡 Próximo

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
