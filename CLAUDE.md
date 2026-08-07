# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚡ Workflow — read this first

**Before touching any code, read `TASKS.md` in this directory.**

1. Take the first task from `## 🟡 Próximo`
2. Move it to `## 🔴 En progreso`
3. Work exclusively on that task — if anything is unclear, ask before implementing
4. When done: move it to `## ✅ Completadas` with one line of notes (what you did and why)
5. Never work on tasks not defined in TASKS.md without explicit confirmation

For cross-repo context (current milestone, blocked tasks), read `../TASKS.md`.

---

## Essential Commands

### Development Server
```bash
php spark serve                  # Start dev server at http://localhost:8180
```

### Testing

Prefer the `composer test*` scripts below — they pass `--no-coverage`. Running `vendor/bin/phpunit`
directly (no flag) triggers a harmless `XDEBUG_MODE=coverage` warning because `phpunit.xml`
declares a `<coverage>` block for `test:coverage` but xdebug isn't active by default; the run still
passes, but its exit code becomes non-zero on the warning, which can trip up naive CI-style checks.
Add `--no-coverage` yourself if you run phpunit directly.

```bash
# Run all tests
vendor/bin/phpunit
vendor/bin/phpunit --testdox    # Human-readable test output

# Run specific test suites
vendor/bin/phpunit tests/Unit              # Unit tests (fast, no DB)
vendor/bin/phpunit tests/Integration       # Integration tests (with DB)
vendor/bin/phpunit tests/Feature           # Feature/Controller tests (HTTP)

# Composer aliases
composer quality                # Run all quality checks (PHPStan, PHPUnit, etc.)
composer cs-fix                 # Fix code style (PSR-12)
```

### Database & Scaffolding
```bash
php spark migrate                                                        # Run migrations
php spark db:seed RbacBootstrapSeeder                                    # Seed IAM (apps, permissions, roles) — idempotent
php spark users:bootstrap-superadmin --email <e> --password <p>          # Create the first superadmin (requires seeder above)
bash vendor/bin/make-crud.sh {Name} {Domain} '{fields}' yes [slug]       # Scaffold new CRUD (recommended)
php spark make:crud {Name} --domain {Domain}                             # Alternative: interactive scaffold
php spark module:check {Name} --domain {Domain}                          # Validate scaffold output
```

### OpenAPI Documentation
```bash
php spark swagger:generate      # Generate public/swagger.json from DTOs and app/Documentation/
```

### Environment validation
```bash
php spark env:check             # Validate required env vars + secret strength + prod CORS
php spark env:check --strict    # Treat production-recommended vars as required (CI/CD pipelines)
```
`init.sh` runs `env:check` before migrations, and the GitHub Actions
workflow (`ci.yml`) runs it before tests. The command refuses to pass when
JWT_SECRET_KEY < 64 bytes, contains a placeholder substring (`change-me`,
`your-secret`, etc.), or when `CORS_ALLOWED_ORIGINS` is unset under
`CI_ENVIRONMENT=production`.

## Architecture Overview (Modernized DTO-First)

This is a **Declarative DTO-First Layered REST API** following the pattern: **Controller → [RequestDTO] → Service → Model → Entity → [ResponseDTO]**.

## Authorization (RBAC)

The API ships a granular IAM model under the `Iam` domain:

- Five tables: `applications`, `permissions`, `roles`, `role_permissions`, `user_roles`.
- Single seeded application: `self` (`id=1`). The `users.role` column was removed — authorization is role-driven via the `user_roles` join.
- **Permission code separator is `.` (dot), NOT `:`** — `users.write`, `iam.admin-access`, `metrics.read`. Reason: `Filters::getCleanName()` runs `explode(':', $filter)` without a limit, so `permission:users:write` parses as filter=`permission` with arg=`['users']` and silently drops `:write`.

> **Schema note (2026-05-03 refactor):** the legacy pair `app_user_memberships` + `membership_roles` was replaced by a single `user_roles(user_id, role_id, assigned_at, assigned_by_user_id)` join. Migrations `2026-05-03-100003` … `100007` perform the change (create user_roles, backfill from membership_roles inside a transaction, drop legacy tables). Roles are now **global** (`roles.application_id` was dropped in migration `100006`); per-application scoping lives on `permissions.application_id`.

Key components:
- `app/Filters/PermissionFilter.php` — alias `permission`, used in routes as `permission:<code>` (e.g. `permission:iam.admin-access`).
- `app/Services/Iam/EffectivePermissionsResolver.php` — derives a user's effective permission codes from `user_roles → roles → role_permissions → permissions`.
- `app/Models/UserRoleModel.php` — the join model for user↔role assignments.
- `SessionManager::generateSessionResponse()` — embeds `permissions: string[]` in the `user` object of the login/refresh response, and the JWT carries a `scope` claim with the same codes.
- `app/Database/Seeds/RbacBootstrapSeeder.php` — idempotent seeder for the `self` application, the canonical permission set (`users.read/write`, `files.read/write`, `audit.read`, `metrics.read`, `apikeys.read/write`, `iam.admin-access`, `iam.superadmin-access`), and the three system roles (`superadmin`, `admin`, `user`) with their default permission grants. Must run before `php spark users:bootstrap-superadmin`, which now attaches the `superadmin` role via a `user_roles` row.

REST endpoints live under `/api/v1/iam/` (all gated by `permission:iam.admin-access`):
- `roles` CRUD + `roles/{id}/permissions` (list/attach/detach)
- `permissions` CRUD
- `users/{user_id}/permissions?application_id=N` (effective permissions)

Role assignment to users happens directly through the **Users** module (`/api/v1/users/{id}` accepts `role_ids[]` in the payload), not through a separate membership resource.

### User modification rules

- `PATCH /api/v1/auth/me` — self-update endpoint. Authenticated user only. Allowlist: `first_name`, `last_name`, `avatar_url`. Email/password/role assignments are not part of the DTO and are silently ignored if sent. Subject id comes from the JWT, never the body.
- `PUT /api/v1/users/{id}` — admin endpoint. Gated by `permission:users.write`. Still rejects self-edit (`assertNotSelf`) and operating on superadmins by non-superadmins (`assertCanActOnSubject`). **Email change requires superadmin** — anything else gets `403 Iam.cannotModifyEmail` (enforced in `UpdateUserAction::execute`).

When scaffolding new modules, `vendor/bin/make-crud.sh` emits the protected route filters configured in `app/Config/Scaffolding.php`. The default `protectedRouteFilters` is `['jwtauth', 'permission:iam.superadmin-access', 'throttle']`. The legacy `iam.admin-access` was removed in May 2026.

### Key Design Principles

1. **DTO-First Shield:** Data validation is an intrinsic property of the DTO. Request DTOs must extend `BaseRequestDTO`.
2. **Auto-Validation:** The `BaseRequestDTO` constructor handles validation automatically via `rules()`. If an object exists, it is valid.
3. **Pure & Transactional Services:** Services extend `BaseCrudService`, are agnostic to HTTP, and use the `HandlesTransactions` trait.
4. **Declarative Controllers:** Controllers extend `ApiController` and use `handleRequest()` to orchestrate the flow without boilerplate.
5. **Output Normalization:** `ApiController` wraps normalized service outcomes and maps paginated DTO shapes to canonical paginated responses.

## Implementation Guidelines

### 1. Request DTOs (`app/DTO/Request/`)
- Must extend `BaseRequestDTO`.
- Implement `rules()` and `map(array $data)`.
- Use PHP 8.2 `readonly` classes.
- **NO manual validation calls in services.**

### 2. Response DTOs (`app/DTO/Response/`)
- Define the contract for the client. Include OpenAPI attributes.
- Use `fromArray(array $data)` static method.

### 3. Services (`app/Services/`)
- Extend `BaseCrudService` for standard CRUD.
- Use `HandlesTransactions` trait for state changes.
- Return DTOs for read workflows and `OperationResult` for command-style workflows. Throw exceptions for errors.
- Implement `applyBaseCriteria()` for global security filters.

### 4. Controllers (`app/Controllers/Api/V1/`)
- Must extend `ApiController`.
- Resolve default service explicitly in `resolveDefaultService()`.
- Use declarative handling: `return $this->handleRequest('methodName', RequestDTO::class);`.

### 5. Documentation
- Schemas live in DTOs. Endpoints live in `app/Documentation/{Domain}/`.

## Testing Strategy

### Unit Tests
- **Services:** Test logic by asserting against DTO return types. Mock dependencies.
- **DTOs:** Test that the constructor throws `ValidationException` for invalid data.

### Feature/Integration Tests
- Verify JSON structure and status codes (201 for creation, 202 for pending, 422 for validation).

## Common Pitfalls (DO NOT DO)
- ❌ Using `InputValidationService` or `validateOrFail` manual calls (Legacy).
- ❌ Returning `ApiResponse` from a service.
- ❌ Passing raw arrays to service methods.
- ❌ Not using `wrapInTransaction` for state-changing operations.

## Static analysis & quality

PHPStan runs at **level 8** with a `phpstan-baseline.neon`. **Current baseline: 0 entries** (fully drained). New code must not introduce any errors against the level-8 ruleset. Run `vendor/bin/phpstan analyse --no-progress` before pushing; the CI gate enforces this. The framework-noise patterns (CI4 helpers, constants,
magic methods) live in `phpstan.neon` under `ignoreErrors:` — keep them
narrow.

Run `composer quality` (PHPStan + PHPUnit + CS-Fixer + swagger-validate)
locally before pushing. CI runs the same. `composer cs-fix` auto-fixes
style violations; the pre-commit hook also runs it on staged files.

## Single Source of Truth

For architecture rules and onboarding, prefer:

1. `vendor/dcardenasl/ci4-api-core/docs/ARCHITECTURE_CONTRACT.md` (authoritative — ships with the package)
2. `docs/template/MODULE_BOOTSTRAP_CHECKLIST.md`
3. `docs/template/CRUD_FROM_ZERO.md`
4. `docs/template/QUALITY_GATES.md`

Base classes (`ApiController`, `BaseCrudService`, `BaseRequestDTO`, `BaseAuditableModel`,
`ApiException` family, `ApiResponse`, `ApiResult`, `OperationResult`, `HandlesTransactions`,
`Auditable`, `ContextHolder`, etc.) live in `dcardenasl/ci4-api-core` (namespace
`dcardenasl\Ci4ApiCore\…`). They are not duplicated in `app/` — the starter only
contains domain code.

## CRUD Scaffolding

Scaffolding is provided by the `dcardenasl/ci4-api-scaffolding` package (installed as a Composer **dev** dependency; in this monorepo it is consumed via path repository pointing at `../ci4-api-scaffolding`). The runtime base classes used by every module live in the separate `dcardenasl/ci4-api-core` package. Consumer config lives in `app/Config/Scaffolding.php` (a one-liner returning `ScaffoldingConfig::defaults()`).

### Quick Start
```bash
bash vendor/bin/make-crud.sh ResourceName DomainName \
    'field1:type:required|searchable,field2:type' \
    yes
```

**IMPORTANT:** Always wrap the fields argument in SINGLE QUOTES — pipes (`|`) are shell-special and will be consumed by the shell otherwise.

### Full signature

```bash
bash vendor/bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]
```

Options:
- `--dry-run` — preview files and wiring without writing anything
- `--no-wire` — skip injecting service/mapper into `app/Config/` (use when wiring manually)

Examples:

```bash
# Default route (auto-pluralized):
bash vendor/bin/make-crud.sh User Users 'name:string:required|searchable,email:string:required|unique' yes

# Custom route (when it differs from the resource name):
bash vendor/bin/make-crud.sh UpaEvent Events 'title:string:required|searchable,year:int:required|filterable' yes upa-events

# Lookup table (no soft delete):
bash vendor/bin/make-crud.sh Permission Users 'name:string:required|searchable' no
```

### ALWAYS use `vendor/bin/make-crud.sh` — never `php spark make:crud` directly

In non-TTY environments (Claude Code, CI/CD, parallel calls), `php spark make:crud` can enter interactive mode if `--fields` arrives empty due to shell pipe expansion. `vendor/bin/make-crud.sh` handles quoting correctly and adds validation steps.

### Restart server after scaffolding

Adding a new route file (`app/Config/Routes/v1/{domain}.php`) requires restarting `php spark serve`. Routes are not detected hot.

```bash
pkill -f 'spark serve'; php spark serve --port 8180 &
```

## Adding a Gallery to a Domain

`GalleryService` (`app/Services/Core/GalleryService.php`) is reusable across any
domain that needs an N:M pivot table between a parent (Show, Course,
Exhibition, Page, …) and the shared `files` table. To wire it for a new domain:

1. **Migration:** create `<entity>_galleries` (or whatever name fits) with at
   minimum `id`, `<entity>_id`, `file_id`, `sort_order`, `is_active`,
   `created_at`, `updated_at`.
2. **Model:** a thin CI4 `Model` pointing at that table with
   `$returnType = 'object'` and the relevant `$allowedFields`.
3. **Pivot repository:** extend `App\Repositories\Common\PivotRepository` and
   declare the FK column name:
   ```php
   class ShowsGalleryRepository extends PivotRepository {
       public function getParentKey(): string { return 'show_id'; }
   }
   ```
4. **Wire in `Config\Services`:** the gallery service receives the pivot
   repository plus `FileRepositoryInterface` (already registered):
   ```php
   public function showsGalleryService(bool $getShared = true): GalleryService {
       if ($getShared) return static::getSharedInstance('showsGalleryService');
       return new GalleryService(
           new ShowsGalleryRepository(model(ShowsGalleryModel::class)),
           service('fileRepository'),
       );
   }
   ```
5. **Controller:** add `use HasGalleryActions;` (`app/Traits/Controllers/`) and
   `protected function galleryService(): GalleryService { return service('showsGalleryService'); }`. Routes follow the convention in the trait's docblock.

The service is fully decoupled from `\CodeIgniter\Model` — it talks to the
pivot via `PivotRepositoryInterface` and to files via `FileRepositoryInterface`.

## Routing Conventions

Route files in `app/Config/Routes/v1/*.php` are auto-discovered and grouped under `api/v1`. Use one file per consumer profile, not per CRUD resource:

- **JWT-authenticated user routes** → their domain file (`auth.php`, `files.php`, `users.php`, `iam.php`, …). Filter chain typically `['jwtauth', 'throttle']` plus a `permission:<code>` where applicable.
- **Public, app-key only routes** → `public.php`. Filter chain `['appKeyRequired', 'throttle']`. Use this for endpoints consumed by a public web/mobile frontend that has no logged-in user but must still authenticate the *application* via `X-App-Key`. The `appKeyRequired` filter returns 401 when the header is missing and 403 when the key is unknown or revoked (RFC 7235).
- **Admin-only routes** → `admin.php`. JWT plus a role/permission gate.

`AppKeyRequiredFilter` validates against the `api_keys` table and is throttled per-key by `ApiKeyThrottleHelpers`. Issue keys via the `apiKeys` admin endpoints; rotate by revoking and re-issuing.

## Troubleshooting

### Pre-commit hook fails on generated files (PHP CS Fixer)
**Cause:** Generated code may have PSR-12 style violations (blank lines in constructors, missing EOF newlines)  
**Fix:** Use `vendor/bin/make-crud.sh` which runs `composer cs-fix` automatically. If hook still fails:

```bash
composer cs-fix           # Auto-fix style violations
git add -u                # Re-stage modified files
git commit                # Now passes the hook
```

**Do NOT use `--no-verify`** except for documented emergencies. The hook exists to catch these issues.

### Notes

1. `make:crud` generates a migration file automatically. Review it after scaffolding, then run `php spark migrate` to apply it.
2. Default persistence for CRUD is `GenericRepository`; create dedicated repositories only for non-trivial domain queries.
