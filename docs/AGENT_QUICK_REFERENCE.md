# Agent Quick Reference — `teatromuseo-api`

This repository is the Teatro Museo Hub. Read `CLAUDE.md` and `TASKS.md` before
editing. It owns users, RBAC/IAM, JWT issuance, and Hub-hosted domains.

## Commands

```bash
php spark serve --port 8180

# CRUD: always use the shell wrapper in non-interactive environments.
bash vendor/bin/make-crud.sh ResourceName DomainName 'field:type:rules,...' yes [route]
php spark module:check ResourceName --domain DomainName
php spark migrate
php spark swagger:generate

composer test:unit
composer test:integration
composer test:feature
composer quality
composer cs-fix
```

Restart the server after scaffolding because route files are not hot-reloaded.

## Architecture rules

```text
Controller → RequestDTO → Service → Model/Entity → ResponseDTO
```

- Request DTOs extend `BaseRequestDTO`, use `readonly` classes, and validate in
  their constructors.
- Services are HTTP-agnostic; reads return DTOs and command flows return
  `OperationResult` or throw exceptions.
- Controllers extend `ApiController`, resolve the default service explicitly,
  and use `handleRequest()`.
- OpenAPI schemas belong in DTO attributes; endpoint documentation belongs in
  `app/Documentation/`.
- Permission codes use `.` (`users.write`, `iam.admin-access`), never `:`.
- The Hub is the only application that issues JWTs and owns IAM tables.

## Structure

- `app/DTO/Request/` and `app/DTO/Response/` — API contracts.
- `app/Controllers/Api/V1/` — HTTP boundary.
- `app/Services/` — business logic.
- `app/Models/`, `app/Entities/`, `app/Repositories/` — persistence.
- `app/Config/Routes/v1/` — versioned routes.
- `tests/Unit`, `tests/Integration`, `tests/Feature` — test suites.

Never commit `.env`, tokens, or credentials. Prefer the Composer test scripts
because they disable coverage when Xdebug is unavailable.
