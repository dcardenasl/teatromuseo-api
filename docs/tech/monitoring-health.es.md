# Monitoreo y salud

Los endpoints de salud exponen `readiness` y `liveness`.

Archivos clave:
- `app/Controllers/Api/V1/HealthController.php`
- `app/Libraries/Monitoring/HealthChecker.php`
- `app/Config/Routes.php`

Endpoints:
- `GET /health` (resumen completo)
- `GET /ping` (OK rápido)
- `GET /ready` (`readiness` de base de datos)
- `GET /live` (`liveness`)

Notas:
- `checkAll()` incluye base de datos, espacio en disco y carpetas `writable`.
- Existen checks para cola, email y Redis, pero no se incluyen por defecto en `checkAll()`.
- Estos endpoints son operativos/de monitoreo y deliberadamente usan un payload propio (no `ApiResponse`).

## Telemetría de feature toggles

Cada evaluación de toggle emite una métrica `feature_toggle` con:
- `metric_value`: `1` cuando está habilitado, `0` cuando no lo está.
- `tags.feature`: nombre del toggle.
- `tags.enabled`: `1` o `0`.

Consulta `feature_toggle` vía `GET /api/v1/metrics/custom/feature_toggle` y filtra por etiquetas para ver cuántas evaluaciones hubo y su resultado.
