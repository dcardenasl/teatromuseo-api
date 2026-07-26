# Runbook 02 — Recuperarse de una migración fallida

**Severidad:** Alta (producción puede quedar en estado mixed-schema) | **ETA:** 5–60 min según la migración | **Auditoría:** B11.2

## Cuándo usar

- `php spark migrate` salió con código no-cero en un deploy de producción.
- Un pod de k8s deploy job está en `Error` con el comando de migración en sus logs.
- Un usuario reporta queries fallando con "unknown column" o "table doesn't exist" justo tras un deploy.

## Acción crítica primero — detener nuevos deploys

```bash
# Kubernetes
kubectl scale deployment/ci4-api-starter --replicas=0   # cortar tráfico nuevo
# O pinear al último tag de imagen bueno si hay pods sanos mezclados con rotos

# Deshabilitar auto-deploy de CI temporalmente
gh workflow disable cd.yml -R dcardenasl/ci4-api-starter
```

Un segundo intento de `migrate` desde CI/CD mientras investigas empeora todo.

## Diagnóstico

### Paso 1 — Inspeccionar el log de migraciones

```bash
mysql -e "
  SELECT version, class, group, namespace, time, batch
  FROM migrations
  ORDER BY id DESC
  LIMIT 5;
" "$DB_NAME"
```

Comparar con `app/Database/Migrations/` para identificar qué migración era la siguiente prevista.

### Paso 2 — Identificar qué se aplicó realmente

Dos modos de falla:

- **DDL fallido** (e.g. CREATE TABLE falló a mitad). DDL es no-transaccional en MySQL — los cambios de statements anteriores en la misma migración pueden haberse comiteado. Inspeccionar con `SHOW TABLES`, `DESCRIBE <table>`, `SHOW INDEX FROM <table>`.
- **Backfill de datos fallido** (e.g. `2026-05-03-100004_MigrateMembershipRolesToUserRoles`). Estas migraciones envuelven su trabajo en `transStart()`/`transComplete()` — si el body throwea, la transacción rollbackea limpiamente. Confirmar contando filas en las tablas afectadas.

### Paso 3 — Elegir camino de recuperación

| Síntoma | Camino |
|---|---|
| Fila de migración ausente + sin artefactos de schema | **Re-correr** — la migración rollbackeó limpio. |
| Fila ausente + artefactos parciales de schema | **Cleanup manual** y re-correr. Drop la tabla/columna parcial antes de reintentar. |
| Fila PRESENTE + schema claramente roto | **Down + up.** El `down()` debe revertir el cambio. Tras éxito, arreglar código de migración y re-correr. |
| `down()` no existe o falla | **Reparación manual.** Aplicar el schema faltante a mano desde el end-state intencional, luego `INSERT INTO migrations` para marcarlo done. |

## Recuperación

### Camino A: Re-corrida limpia

```bash
php spark migrate
mysql -e "SELECT class FROM migrations ORDER BY id DESC LIMIT 3;" "$DB_NAME"
# Confirmar que la migración fallida ya está en la tabla.
```

### Camino B: Cleanup manual, luego re-correr

```bash
mysql "$DB_NAME"
> -- Inspeccionar qué se creó
> SHOW CREATE TABLE the_partial_table;
> -- Drop / un-add según necesario
> DROP TABLE the_partial_table;
EXIT;

php spark migrate
```

### Camino C: Down luego up

```bash
php spark migrate:rollback -b $(mysql -Nse "SELECT MAX(batch) FROM migrations;" "$DB_NAME")
mysql -e "SELECT class FROM migrations ORDER BY id DESC LIMIT 3;" "$DB_NAME"

php spark migrate
```

### Camino D: Reparación manual (último recurso)

Solo cuando `down()` no esté disponible / sea inseguro. **Documentar cada SQL que corras** para que el post-mortem capture en qué estado estuvo producción.

## Restaurar tráfico

```bash
php spark migrate:status

kubectl scale deployment/ci4-api-starter --replicas=$DESIRED_REPLICAS
gh workflow enable cd.yml -R dcardenasl/ci4-api-starter
```

## Checklist post-mortem

- [ ] ¿Por qué falló la migración? Locking? FK contra target inexistente? SQL malo?
- [ ] ¿Era reproducible en staging? Si no, ¿qué difería?
- [ ] Agregar test que lo hubiera capturado (probablemente Integration que corre la migración contra DB fresca).
- [ ] Si la migración era no-idempotente y la recuperación requirió SQL a mano, abrir issue: "endurecer migración X con `dropIfExists` / `addColumn-if-not-present`."
- [ ] Actualizar este runbook con lo nuevo.
