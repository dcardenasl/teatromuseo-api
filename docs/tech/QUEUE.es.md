# Cola y Jobs (CI4)

Este proyecto incluye una cola en base de datos y un worker CLI. Se usa para flujos de email, request logging y auditoria asincrona.

## Cómo funciona

- Los jobs se guardan en la tabla `jobs`.
- Los jobs fallidos se mueven a `failed_jobs`.
- Un worker CLI procesa jobs desde una cola con nombre.
- Los emails usan la cola `emails`.
- El request logging usa la cola `logs`.
- La auditoria usa la cola `audit` para eventos no criticos.

Ubicaciones principales:
- Config de cola: `app/Config/Queue.php`
- Config async de auditoria: `app/Config/Audit.php`
- Queue manager: `app/Libraries/Queue/QueueManager.php`
- Comando worker: `app/Commands/QueueWork.php`
- Jobs de email: `app/Libraries/Queue/Jobs/SendEmailJob.php`, `SendTemplateEmailJob.php`
- Job de request log: `app/Libraries/Queue/Jobs/LogRequestJob.php`
- Job de auditoria: `app/Libraries/Queue/Jobs/WriteAuditLogJob.php`
- Encolado de email: `app/Services/System/EmailService.php`
- Orquestacion de auditoria: `app/Services/System/AuditService.php`

## Configuración (.env)

```
QUEUE_DRIVER = database
QUEUE_MAX_ATTEMPTS = 3
QUEUE_RETRY_AFTER = 90
AUDIT_ASYNC_ENABLED = true
AUDIT_QUEUE_NAME = audit
AUDIT_MAX_PAYLOAD_BYTES = 60000
```

Notas:
- La implementación actual de `QueueManager` usa tablas de **database**.
- Si pones `QUEUE_DRIVER=redis`, seguirá usando database a menos que se extienda el manager.
- En `ENVIRONMENT=testing`, la conexión de base de datos de cola usa `tests` por defecto (se puede sobrescribir con `QUEUE_DATABASE_CONNECTION`).

## Migraciones requeridas

Ejecuta migraciones para crear las tablas de la cola:

```
php spark migrate
```

Migraciones:
- `app/Database/Migrations/2026-01-29-200038_CreateJobsTable.php`
- `app/Database/Migrations/2026-01-29-200102_CreateFailedJobsTable.php`

## Ejecutar el worker

Procesar continuamente la cola `emails`:

```
php spark queue:work --queue=emails
```

Procesar un solo job y salir:

```
php spark queue:work --queue=emails --once
```

Limitar procesamiento:

```
php spark queue:work --queue=emails --max-jobs=10
```

Procesar jobs de auditoria continuamente:

```
php spark queue:work --queue=audit
```

Procesar multiples colas con workers separados (recomendado):

```
php spark queue:work --queue=emails
php spark queue:work --queue=logs
php spark queue:work --queue=audit
```

## Verificar que funciona

1. Genera un job:
   - Registrar un usuario (email de verificación).
   - Solicitar reset de contraseña.
   - Invocar cualquier endpoint API con request logging habilitado.
2. Confirma que existan registros en `jobs` para las colas esperadas (`emails`, `logs`, `audit`).
3. Corre el worker:
   - `php spark queue:work --queue=emails --once`
4. Confirma que el job se elimina de `jobs` y el email se envía.
5. Si falla, se reintenta y luego pasa a `failed_jobs`.

## Solución de problemas

- No hay jobs en `jobs`:
  - La acción que debía encolar el job no se ejecutó o falló antes del enqueue.
- Los jobs no se procesan:
  - Worker no corriendo, o `--queue` incorrecto.
- Los jobs fallan continuamente:
  - Revisar configuración de email y logs.
- Estado de la cola:
  - `app/Libraries/Monitoring/HealthChecker.php` valida `jobs` y reporta conteos.

## Recomendado (Desarrollo/Producción)

Desarrollo:
- Correr el worker en otra terminal mientras se prueban features.

Producción:
- Ejecutar workers de cola bajo un supervisor (systemd, supervisor, PM2, etc.) para cada cola activa (`emails`, `logs`, `audit`).
