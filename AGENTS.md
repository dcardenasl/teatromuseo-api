# teatromuseo-api — Central Hub Repository Guidelines

> **Responsibility:** Central Hub — User management, RBAC/IAM, JWT issuance, File Storage, and Hub-owned domains.
> **Port:** 8180

## Key Architectural Rules

1. **JWT Issuance:** The Hub is the ONLY application in the Teatro Museo platform that issues JWT tokens (`POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`). Domain apps do not issue or decode JWTs directly.
2. **Permission Storage & IAM:** All permissions (`permissions` table), roles (`roles` table), and role-permission bindings (`role_permissions` table) are owned exclusively by the Hub.
3. **Internal HMAC Authentication:** Inter-service callbacks (Hub ↔ Domain) use `HUB_INTERNAL_SECRET` for HMAC-SHA256 signature verification via `HubSignatureFilter`.
4. **DTO-First & ApiController:** All API endpoints must extend `ApiController`, use `handleRequest()`, and return responses wrapped in `ApiResponse`.
5. **Quality Gates:** Must run `composer quality` before committing (PHPStan Level 8, CS-Fixer, OpenAPI validation, PHPUnit).
