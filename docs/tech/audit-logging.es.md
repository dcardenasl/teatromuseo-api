# Registro de Auditoría

Los registros de auditoría capturan acciones de creación, actualización y eliminación sobre entidades, más eventos de control de seguridad.

Archivos clave:
- `app/Services/System/AuditService.php`
- `app/Services/System/AuditWriter.php`
- `app/Traits/Auditable.php`
- `app/Models/AuditLogModel.php`
- `app/Libraries/Queue/Jobs/WriteAuditLogJob.php`
- `app/Config/Audit.php`
- `app/Database/Migrations/2026-01-29-205241_CreateAuditLogsTable.php`

## Integración con Modelos (`Auditable`)

Cualquier modelo que requiera un rastro de auditoría debe usar el trait `App\Traits\Auditable`.

### Configuración mediante DI
Para mantener la pureza de los servicios, los modelos ya no utilizan llamadas estáticas a servicios. Deben configurarse con una `AuditServiceInterface` inyectada (típicamente mediante `Config\Services` o un Proveedor de Servicios de Dominio):

```php
// app/Config/RepositoryModelServices.php
public static function productModel()
{
    $model = new \App\Models\ProductModel();
    // Inyectar el servicio explícitamente
    $model->setAuditService(static::auditService());
    $model->initAuditable();
    return $model;
}
```

### Sanitización y Seguridad
- **AuditPayloadSanitizer:** Todos los payloads pasan por un sanitizador que elimina tokens y secretos sensibles antes de la persistencia.
- **Sanitización a Nivel de Entidad:** `UserEntity::toArray()` elimina explícitamente `password`, `reset_token` y otros campos sensibles. Esto asegura que incluso si se pasa una entidad directamente al sistema de auditoría, los datos sensibles nunca se registren.

## Comportamiento en Runtime (Modo Híbrido)
- Los eventos críticos de auditoría se persisten de forma sincrónica (best effort).
- Los eventos no críticos se encolan de forma asíncrona en la cola `audit`.
- Si falla el enqueue, `AuditService` hace fallback a persistencia sincrónica.
- Los payloads se limitan por tamaño antes de encolar para proteger `jobs.payload`.

Críticos por defecto:
- Cualquier evento con `severity=critical`.
- Acciones en `Config\\Audit::$criticalActions`:
  - `authorization_denied_role`
  - `api_key_auth_failed`
  - `api_key_rate_limit_exceeded`
  - `revoked_token_reuse_detected`

## Configuración
- `AUDIT_ASYNC_ENABLED` (default: `true`, en testing default `false`)
- `AUDIT_QUEUE_NAME` (default: `audit`)
- `AUDIT_MAX_PAYLOAD_BYTES` (default: `60000`)

## Operación
- Ejecutar workers incluyendo la cola de auditoría, por ejemplo:
  - `php spark queue:work --queue=audit`
- Monitorear backlog y fallos:
  - `jobs` filtrado por `queue='audit'`
  - `failed_jobs` filtrado por `queue='audit'`

Notas:
- Los registros se guardan en `audit_logs`.
- Los endpoints están en `app/Controllers/Api/V1/Admin/AuditController.php`.
- La validación de entrada para acciones de auditoría (`index`, `show`, `by_entity`) está centralizada y la consume `AuditService`.
