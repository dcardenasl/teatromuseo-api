# Registro de solicitudes

Las solicitudes pueden registrarse de forma asíncrona vía un job en cola.

Archivos clave:
- `app/Filters/RequestLoggingFilter.php`
- `app/Libraries/Queue/Jobs/LogRequestJob.php`
- `app/Database/Migrations/2026-01-29-201621_CreateRequestLogsTable.php`
- `app/Models/RequestLogModel.php`

Variables de entorno:
- `REQUEST_LOGGING_ENABLED`
- `SLOW_QUERY_THRESHOLD`

Notas:
- Los logs se almacenan en `request_logs`.
- Los logs se encolan en la cola `logs`.
