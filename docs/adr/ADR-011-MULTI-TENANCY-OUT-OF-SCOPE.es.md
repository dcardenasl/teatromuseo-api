# ADR-011: Multi-tenancy fuera de alcance para v1.x

## Estado
Aceptado (auditoría B11.1, 2026-05-07)

## Contexto

Varios proyectos derivados del kit han hecho la misma pregunta: "¿puede hospedar múltiples tenants en un solo despliegue?" La respuesta histórica ha sido "sí, con cirugía". La auditoría (mayo 2026, hallazgo F31) marcó que el kit no lo dice explícitamente — el silencio se lee como "sí soportamos" hasta que un bug de scoping de tenant muerde.

Este ADR fija la posición de forma explícita para que contribuyentes futuros no intenten retrofittear medio modelo multi-tenant, y para que proyectos futuros adopten el kit sabiendo qué forma están eligiendo.

## Decisión

**v1.x es single-tenant.** El modelo de datos, el modelo de autorización y el ciclo de vida de la request asumen una organización por despliegue.

Específicamente:

- `users.email` tiene un único índice global único. Dos tenants con el mismo email de admin no pueden coexistir.
- `applications` existe en el esquema (la fila `code = 'self'` es la sembrada) pero **NO es un scope de tenant** — es un concepto de scoping de permisos que permite a un único tenant hospedar múltiples apps registradas (admin, mobile, integraciones de terceros) compartiendo el mismo pool de usuarios. Renombrarla invitaría a la suposición equivocada.
- `permissions.application_id` scopea códigos de permiso por aplicación, no por tenant.
- `BaseAuditableModel` no tiene columna de tenant; las queries no filtran por tenant.
- Las entradas del audit log no registran contexto de tenant.
- Los uploads de archivos por defecto van por scope de usuario (`FILES_USER_SCOPED=true`); no tienen scope de tenant alguno.

Si un proyecto necesita multi-tenancy, el camino soportado es **forkear** el kit (o generar un proyecto desde él via `new-project.sh`) y agregar la columna de tenant donde corresponda. No prometemos un upgrade non-breaking de regreso desde un fork tenanted.

## Consecuencias

### Positivas

- Cada capa se mantiene más simple. Sin middleware de resolución de tenant, sin scopes globales ocultos en modelos al estilo Eloquent, sin la carga de revisar "¿olvidé el filtro de tenant en esta query?".
- El seeder, el comando bootstrap-superadmin, los ejemplos de OpenAPI — todos se mantienen legibles para alguien que ve el kit por primera vez.
- Las queries de audit log no requieren un `WHERE tenant_id = ?` extra.

### Negativas

- Los equipos que necesitan multi-tenancy deben hacer el trabajo real ellos. El kit les da un punto de partida limpio, no un atajo.
- Un v2 futuro que agregue tenancy será un major bump con migraciones de esquema breaking. No es un problema hasta que pasa.

### Neutras

- La tabla `applications` puede ser repropósita como tabla de tenants por un fork determinado, pero esto requiere también agregar `tenant_id` a `users`, `audit_logs`, `files`, y probablemente `roles`/`role_permissions`. No lo recomendamos — empezar desde cero.

## Cómo se ve "forkear bien" (cuando se necesite)

Para equipos que decidan que lo necesitan:

1. Agregar `tenant_id INT UNSIGNED NOT NULL` a `users`, `audit_logs`, `files`, y cualquier otra tabla de dominio.
2. Reemplazar el unique global en `users.email` por `(tenant_id, email)`.
3. Introducir un `TenantContext` static (espejo de `ContextHolder`) poblado desde el claim `tenant_id` del JWT.
4. Inyectar un override de `BaseAuditableModel::applyBaseCriteria()` que scopee cada query por `tenant_id`.
5. Actualizar `EffectivePermissionsResolver` para filtrar por tenant, no solo por aplicación.
6. Actualizar `RbacBootstrapSeeder` para sembrar roles por tenant o aceptar que los roles son globales.

Son aproximadamente 2-3 semanas de trabajo cuidadoso, más su propia superficie de tests. Mucho más barato si se planifica desde el inicio que si se retrofittea.

## Punteros

- Hallazgo de auditoría F31 (mayo 2026) — "Multi-tenancy fuera de alcance pero no documentado explícitamente como single-tenant kit".
- El README de proyectos derivados debería reafirmar esta posición. Actualizado junto con este ADR.
