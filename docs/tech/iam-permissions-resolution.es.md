# Resolución de permisos IAM

Cómo el hub resuelve los permisos efectivos de un usuario, y el invariante que todo seeder debe respetar.

## Cómo funciona

Al hacer login, `SessionManager` llama a `EffectivePermissionsResolver::resolveAll($userId)`.

`EffectivePermissionsResolver::loadAll()` ejecuta:

```sql
-- Usuarios normales: todos los permisos de todas las aplicaciones que cubren sus roles
SELECT DISTINCT p.code
FROM user_roles ur
JOIN role_permissions rp ON rp.role_id = ur.role_id
JOIN permissions p       ON p.id = rp.permission_id
WHERE ur.user_id = :userId
ORDER BY p.code ASC

-- Superadmins: todos los permisos de la base de datos, sin filtro de app
SELECT code FROM permissions ORDER BY code ASC
```

Los códigos resultantes se embeben en el scope del JWT y en `user.permissions` de la respuesta de login. El helper `has_permission()` del admin panel lee de ese valor de sesión.

## Registro de aplicaciones

| `applications.code` | `id` | Usado por |
|---------------------|------|-----------|
| `self`              | 1    | Permisos propios del hub (`iam.*`, `users.*`, `files.*`, `audit.*`, etc.) |
| `cms`               | 3    | Permisos CMS (`cms.*`) registrados por `domain:sync-permissions` |

`domain:sync-permissions` registra cada permiso `cms.*` únicamente bajo la aplicación `cms`. Los seeders del hub deben referenciar esa misma aplicación al asignar permisos a roles.

## Invariante para seeders

> **Todo seeder debe usar el código de aplicación correcto para los permisos que asigna.**

- Permisos del hub (`iam.*`, `users.*`, etc.) → `application_code = 'self'` (via `RbacBootstrapSeeder`)
- Permisos CMS (`cms.*`) → `application_code = 'cms'` (via `CmsRolesSeeder` después de `domain:sync-permissions`)

`resolveAll()` agrega permisos de todas las aplicaciones, por lo que no es necesario duplicar permisos en `self`. Usar el código de aplicación correcto es todo lo que se requiere.

```php
// CORRECTO — los roles CMS cargan desde la aplicación cms
private const DOMAIN_APP_CODE = 'cms';

// INCORRECTO — era un workaround de la limitación antigua de resolve($userId, 1)
private const DOMAIN_APP_CODE = 'self';
```

Ver `app/Database/Seeds/CmsRolesSeeder.php` como ejemplo de referencia.

## Claves de caché

| Patrón de clave | Llenada por | Borrada por |
|-----------------|-------------|-------------|
| `iam_eff_perms_{userId}_{appId}` | `resolve($userId, $appId)` | `invalidateForUser()`, `invalidateAll()` |
| `iam_eff_perms_all_{userId}` | `resolveAll($userId)` | `invalidateForUser()`, `invalidateAll()` |

TTL: 60 segundos en ambas.

## Depurar permisos vacíos

Si un usuario hace login y `user.permissions` está vacío (o faltan códigos esperados) aunque su rol tenga esos permisos en la DB:

1. Verificar a qué `application_id` apuntan los permisos del rol:
   ```sql
   SELECT p.code, p.application_id, a.code AS app
   FROM permissions p
   JOIN role_permissions rp ON rp.permission_id = p.id
   JOIN roles r              ON r.id = rp.role_id
   JOIN applications a       ON a.id = p.application_id
   WHERE r.code = 'cms-editor'
   ORDER BY p.application_id, p.code;
   ```
   Esperado para roles CMS: `app = cms`. Si aparece `app = self` para códigos `cms.*`, el seeder usó el código de aplicación incorrecto.

2. Volver a correr el seeder con `DOMAIN_APP_CODE = 'cms'` correcto.

3. Limpiar el cache de permisos (TTL de 60 segundos — el cache obsoleto puede ocultar un fix reciente):
   ```bash
   php spark cache:clear
   ```

4. Probar el login de nuevo:
   ```bash
   curl -s -X POST http://localhost:8180/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"usuario@ejemplo.com","password":"..."}' \
     | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['user']['permissions'])"
   ```

## Migrar desde el workaround de mirroring

Si se usó `domain:sync-permissions --mirror-to-self` anteriormente, existen permisos `cms.*` duplicados bajo la aplicación `self`. Eliminarlos:

```bash
# Vista previa de lo que se eliminaría
php spark iam:remove-mirrored-permissions --dry-run

# Eliminar (idempotente — seguro de correr múltiples veces)
php spark iam:remove-mirrored-permissions

# Re-seedear roles CMS para vincularlos a la aplicación cms correcta
php spark db:seed CmsRolesSeeder
```

## Archivos clave

| Archivo | Rol |
|---------|-----|
| `app/Services/Iam/EffectivePermissionsResolver.php` | `resolveAll()` — agregación cross-app; `resolve()` — por app (usado por introspect M2M) |
| `app/Services/Auth/Support/SessionManager.php` | Llama `resolveAll()` al login |
| `app/Services/Auth/AuthService.php` | Llama `resolveAll()` en `me()` y `updateMe()` |
| `app/Services/Tokens/RefreshTokenService.php` | Llama `resolveAll()` al refrescar token |
| `app/Database/Seeds/CmsRolesSeeder.php` | Seeder de referencia usando código de app `'cms'` |
| `app/Database/Seeds/RbacBootstrapSeeder.php` | Siembra los permisos propios del hub (`self`) |
| `app/Commands/RemoveMirroredPermissions.php` | Comando de limpieza para copias duplicadas |
