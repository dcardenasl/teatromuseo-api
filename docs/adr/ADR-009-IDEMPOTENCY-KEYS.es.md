# ADR-009: Soporte `Idempotency-Key` para requests que cambian estado

## Estado
Aceptado (auditoría B7.3, 2026-05-06)

## Contexto

Las requests HTTP que cambian estado (POST / PUT / PATCH / DELETE) sufren una clase de fallos de sistemas distribuidos fácil de ignorar a tráfico bajo y devastadora a escala:

- Un parpadeo de red entre cliente y servidor. El cliente reintenta. **El servidor procesó la request original con éxito** pero el cliente nunca recibió el 2xx. Ahora hay dos del recurso que se debía crear.
- Las integraciones SaaS (pagos, feeds de RR.HH., entrega de push móvil) no toleran writes duplicados. La mitigación estándar de la industria, usada por Stripe, AWS, GitHub y otros, es el header `Idempotency-Key`.

Antes de este ADR, ningún endpoint del kit honraba el header. Un reintento creaba duplicados silenciosamente.

## Decisión

1. **Introducir un `IdempotencyFilter` opt-in** registrado como alias `idempotency`. Las rutas que opt-in obtienen el contrato; las que no, quedan inalteradas. **No** cableado en globals — un endpoint de solo lectura nunca necesita idempotencia, y agregarlo en todos lados infla la tabla cache innecesariamente.

2. **Almacenamiento en la tabla `idempotency_keys`** (migración `2026-05-06-100000_CreateIdempotencyKeysTable`):
   - `idempotency_key` VARCHAR(64) PK — provista por el cliente.
   - `actor_id` INT NULL — identifica al subject autenticado (NULL para anónimo / service tokens).
   - `endpoint` VARCHAR(255) — `METHOD path`, ej. `POST /api/v1/users`.
   - `request_hash` CHAR(64) — SHA-256 del body.
   - `response_status`, `response_headers` (JSON), `response_body` — lo que se replica.
   - `expires_at` DATETIME — TTL = 24 horas (configurable en el filter).
   - Indexes en `expires_at` (para cleanup) y `(actor_id, endpoint)` (para la ruta de lookup).

3. **Matriz de comportamiento:**

   | Escenario | Respuesta del filter |
   |---|---|
   | Sin header `Idempotency-Key` en un método que honramos | Pass-through (sin trabajo extra de DB). |
   | Método fuera de {POST, PUT, PATCH, DELETE} | Pass-through. |
   | Header presente, formato inválido (longitud / charset) | `400` con `Validation.invalidIdempotencyKey`. |
   | Header válido, cache miss | Forward al handler. En respuesta 2xx, persiste row en `after()`. |
   | Header válido, cache hit, mismo body hash | Replica `(status, headers, body)` cacheada + `Idempotent-Replay: true`. |
   | Header válido, cache hit, body hash distinto | `409 Conflict` con `Idempotency-Mismatch: true`. |
   | Handler retorna 4xx/5xx | El pending row **no** se persiste (el cliente puede legítimamente reintentar contra un outcome distinto). |

4. **Restricción del formato de la key:** `[A-Za-z0-9._:+\-]{8,64}`. Suficientemente amplio para UUIDv4, ULIDs, KSUID, y strings al estilo Stripe `sk_live_...`. Cap duro a 64 caracteres para mantener la columna apretada y prevenir abuso de almacenamiento. Rechazar keys demasiado cortas (< 8) protege contra plantillas accidentalmente vacías.

5. **Estrategia de body hashing:** SHA-256 del body crudo de la request. Hasheamos DESPUÉS de que `before()` lea el body, para que vea los bytes que el cliente realmente envió. Los headers están explícitamente fuera del hash — los clientes reintentan legítimamente con un `Authorization` refrescado en rotación de token, y queremos replicar de todos modos.

6. **La persistencia ocurre en `after()`** para registrar la respuesta real. Existe una pequeña ventana de carrera (dos llamadas concurrentes con la misma key ambas hacen miss, ambas reenvían al handler, ambas intentan insertar). El insert se envuelve en `try/catch` para que el perdedor descarte silenciosamente el error de PK duplicado; el ganador queda. Es aceptable porque:
   - Las dos respuestas son equivalentes (mismo body hash).
   - Reintentos posteriores de cualquiera verán la fila persistida y la replicarán.

7. **Estado in-flight entre `before()` y `after()`** vive en `private static ?array $pending`. Es seguro porque PHP-FPM atiende una request por proceso worker a la vez. CI4 instancia filters frescos por fase (`new $className()` en ambos `before` y `after`), así que el estado de instancia no es confiable. El static lleva la tupla (key, hash, endpoint, actor) entre fases de la misma request.

8. **Convención opt-in:** las rutas aplican el filter via `['filter' => 'idempotency']` en su definición. El owner de la ruta decide conscientemente qué mutaciones son idempotency-safe (la mayoría sí; algunas, como transferencias de fondos, explícitamente no — esas deberían rechazar llamadas sin el header de plano, lo cual es una mejora futura no incluida en v1.0).

## Consecuencias

### Positivas
- Seguridad ante reintentos de red para cualquier ruta opt-in al costo de una fila extra + un lookup por write.
- Contrato estándar de la industria que los integradores (apps móviles, terceros) reconocen de Stripe / AWS / GitHub.
- La ruta de replicación es barata (lookup por PK, lectura de una fila) así que los endpoints opt-in se mantienen rápidos bajo tormentas de retry.
- El mismatch de hash de body expone un bug real del cliente (reuso de key con payload distinto) en lugar de corromper estado.

### Negativas
- Suma almacenamiento proporcional al tráfico de writes en 24h. A ~1 KB / fila y 100 writes / segundo sostenidos, son ~8.6M filas / día = ~8 GB / día. El job de limpieza es mandatorio a escala.
- El `$pending` static lleva estado entre fases, lo que asume request handling single-threaded. Si nos movemos a un worker estilo Swoole / RoadRunner / Octane que sirve múltiples requests, necesita revisión.
- Rutas que necesiten semántica estricta de body-hash en uploads multipart (donde la misma request lógica puede serializarse distinto) verán falsos 409. Endpoints multipart-pesados deberían opt-out.

### Neutras
- Los tests deben resetear `IdempotencyFilter::flushPending()` entre llamadas en el mismo método de test.

## Punteros de implementación

- Migración: `app/Database/Migrations/2026-05-06-100000_CreateIdempotencyKeysTable.php`.
- Filter: `vendor/dcardenasl/ci4-api-core/src/Http/Filters/IdempotencyFilter.php`. Alias `idempotency` en `Config\Filters::$aliases`.
- Tests: `tests/Feature/Filters/IdempotencyFilterTest.php` — cubre la matriz completa de comportamiento, 6 casos.

## Trabajo futuro

- **Job de limpieza** (`php spark idempotency:gc`) borrando `WHERE expires_at < NOW()`. Cron cada hora.
- **TTL por ruta** (algunos endpoints quieren 5 minutos, otros 7 días). Pasar como argumento del filter: `['filter' => 'idempotency:300']` para TTL de 5 minutos.
- **Modo `Idempotency-Key` requerido** para endpoints de alto riesgo (ej. movimiento de dinero). Un 422 si falta el header en rutas opt-in.
- Reemplazar el `$pending` static por un slot del request-scoped service container cuando CI4 entregue DI por request adecuado (o antes si adoptamos Octane).
