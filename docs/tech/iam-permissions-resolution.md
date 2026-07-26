# IAM Permissions Resolution

How the hub resolves a user's effective permissions, and the invariant every seeder must respect.

## How it works

At login, `SessionManager` calls `EffectivePermissionsResolver::resolveAll($userId)`.

`EffectivePermissionsResolver::loadAll()` runs:

```sql
-- Regular users: all permissions across every application the user's roles cover
SELECT DISTINCT p.code
FROM user_roles ur
JOIN role_permissions rp ON rp.role_id = ur.role_id
JOIN permissions p       ON p.id = rp.permission_id
WHERE ur.user_id = :userId
ORDER BY p.code ASC

-- Superadmins: every permission in the database, no app filter
SELECT code FROM permissions ORDER BY code ASC
```

The resulting codes are embedded in the JWT scope and in `user.permissions` in the login response. The admin panel's `has_permission()` helper reads from that session value.

## Application registry

| `applications.code` | `id` | Used by |
|---------------------|------|---------|
| `self`              | 1    | Hub's own permissions (`iam.*`, `users.*`, `files.*`, `audit.*`, etc.) |
| `cms`               | 3    | CMS permissions (`cms.*`) registered by `domain:sync-permissions` |

`domain:sync-permissions` registers every `cms.*` permission under the `cms` application only. Seeders in the hub must reference that same application when assigning permissions to roles.

## Invariant for seeders

> **Seeders must use the correct application code for the permissions they are assigning.**

- Hub permissions (`iam.*`, `users.*`, etc.) → `application_code = 'self'` (via `RbacBootstrapSeeder`)
- CMS permissions (`cms.*`) → `application_code = 'cms'` (via `CmsRolesSeeder` after `domain:sync-permissions`)

`resolveAll()` aggregates across all applications, so there is no need to mirror permissions into `self`. Using the correct application code is now all that is required.

```php
// CORRECT — CMS roles load from the cms application
private const DOMAIN_APP_CODE = 'cms';

// WRONG — was a workaround for the old resolve($userId, 1) limitation
private const DOMAIN_APP_CODE = 'self';
```

See `app/Database/Seeds/CmsRolesSeeder.php` for a working example.

## Cache keys

| Key pattern | Populated by | Cleared by |
|-------------|--------------|-----------|
| `iam_eff_perms_{userId}_{appId}` | `resolve($userId, $appId)` | `invalidateForUser()`, `invalidateAll()` |
| `iam_eff_perms_all_{userId}` | `resolveAll($userId)` | `invalidateForUser()`, `invalidateAll()` |

TTL: 60 seconds on both.

## Debugging empty permissions

If a user logs in and `user.permissions` is empty (or missing expected codes) despite their role having those permissions in the DB:

1. Check which `application_id` their role's permissions point to:
   ```sql
   SELECT p.code, p.application_id, a.code AS app
   FROM permissions p
   JOIN role_permissions rp ON rp.permission_id = p.id
   JOIN roles r              ON r.id = rp.role_id
   JOIN applications a       ON a.id = p.application_id
   WHERE r.code = 'cms-editor'
   ORDER BY p.application_id, p.code;
   ```
   Expected for CMS roles: `app = cms`. If you see `app = self` for `cms.*` codes, the seeder used the wrong app code.

2. Re-run the seeder with the correct `DOMAIN_APP_CODE = 'cms'`.

3. Clear the permission cache (60-second TTL — stale cache can hide a fresh fix):
   ```bash
   php spark cache:clear
   ```

4. Re-test login:
   ```bash
   curl -s -X POST http://localhost:8180/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"user@example.com","password":"..."}' \
     | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['user']['permissions'])"
   ```

## Migrating from the mirror workaround

If `domain:sync-permissions --mirror-to-self` was used previously, `cms.*` permissions exist as duplicates under the `self` application. Remove them:

```bash
# Preview what would be removed
php spark iam:remove-mirrored-permissions --dry-run

# Remove (idempotent — safe to run multiple times)
php spark iam:remove-mirrored-permissions

# Re-seed CMS roles to link them to the correct cms application
php spark db:seed CmsRolesSeeder
```

## Key files

| File | Role |
|------|------|
| `app/Services/Iam/EffectivePermissionsResolver.php` | `resolveAll()` — cross-app aggregation; `resolve()` — per-app (used by M2M introspect) |
| `app/Services/Auth/Support/SessionManager.php` | Calls `resolveAll()` at login |
| `app/Services/Auth/AuthService.php` | Calls `resolveAll()` for `me()` and `updateMe()` |
| `app/Services/Tokens/RefreshTokenService.php` | Calls `resolveAll()` on token refresh |
| `app/Database/Seeds/CmsRolesSeeder.php` | Reference seeder using `'cms'` app code |
| `app/Database/Seeds/RbacBootstrapSeeder.php` | Seeds hub's own (`self`) permissions |
| `app/Commands/RemoveMirroredPermissions.php` | Cleanup command for stale mirror copies |
