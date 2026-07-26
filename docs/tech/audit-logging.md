# Audit Logging

Audit logs capture create, update, and delete actions on entities, plus security-relevant control events.

Key files:
- `app/Services/System/AuditService.php`
- `app/Services/System/AuditWriter.php`
- `app/Traits/Auditable.php`
- `app/Models/AuditLogModel.php`
- `app/Libraries/Queue/Jobs/WriteAuditLogJob.php`
- `app/Config/Audit.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

## Integration with Models (`Auditable`)

Any model requiring an audit trail should use the `App\Traits\Auditable` trait.

### Setup via DI
To maintain service purity, models no longer use static service calls. They must be configured with an injected `AuditServiceInterface` (typically via `Config\Services` or a Domain Service Provider):

```php
// app/Config/RepositoryModelServices.php
public static function productModel()
{
    $model = new \App\Models\ProductModel();
    // Inject the service explicitly
    $model->setAuditService(static::auditService());
    $model->initAuditable();
    return $model;
}
```

### Sanitization and Security
- **AuditPayloadSanitizer:** All payloads are passed through a sanitizer that strips sensitive tokens and secrets before persistence.
- **Entity Level Sanitization:** `UserEntity::toArray()` explicitly removes `password`, `reset_token`, and other sensitive fields. This ensures that even if an entity is passed directly to the audit system, sensitive data is never logged.

## Runtime Behavior (Hybrid Mode)
- Critical audit events are persisted synchronously (best effort).
- Non-critical events are queued asynchronously in the `audit` queue.
- If enqueue fails, `AuditService` falls back to synchronous persistence.
- Payloads are size-limited before enqueue to protect `jobs.payload`.

Critical by default:
- Any event with `severity=critical`.
- Actions in `Config\\Audit::$criticalActions`:
  - `authorization_denied_role`
  - `api_key_auth_failed`
  - `api_key_rate_limit_exceeded`
  - `revoked_token_reuse_detected`

## Configuration
- `AUDIT_ASYNC_ENABLED` (default: `true`, testing defaults to `false`)
- `AUDIT_QUEUE_NAME` (default: `audit`)
- `AUDIT_MAX_PAYLOAD_BYTES` (default: `60000`)

## Operations
- Run workers including the audit queue, for example:
  - `php spark queue:work --queue=audit`
- Monitor backlog and failures:
  - `jobs` filtered by `queue='audit'`
  - `failed_jobs` filtered by `queue='audit'`

Notes:
- Records are stored in the `audit_logs` table.
- API endpoints are defined under `app/Controllers/Api/V1/Admin/AuditController.php`.
- Input validation for audit actions (`index`, `show`, `by_entity`) is centralized and consumed by `AuditService`.
