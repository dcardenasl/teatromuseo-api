# Convenciones de paginación

Auditoría B7.5 (2026-05-06): aclara la distinción entre `per_page` y `limit` en la API. La auditoría originalmente marcó la diferencia entre `RoleIndexRequestDTO` (`per_page`) y `SlowRequestsQueryRequestDTO` (`limit`) como inconsistencia. Tras revisión, los dos parámetros son **semánticamente distintos** y deben permanecer así — pero la convención necesita estar documentada para que endpoints futuros no elijan el incorrecto.

## Los dos patrones

### `per_page` — endpoints de listado paginado

Usar `per_page` (junto con el parámetro `page`) cuando el endpoint retorna una colección navegable basada en páginas. La respuesta lleva metadatos de paginación para que el cliente pueda obtener páginas adicionales.

**Forma de la request:**
```
GET /api/v1/users?page=2&per_page=20
```

**Forma de la respuesta:**
```json
{
  "status": "success",
  "data": [ ... ],
  "meta": {
    "total": 312,
    "per_page": 20,
    "page": 2,
    "last_page": 16,
    "from": 21,
    "to": 40
  }
}
```

**Usado por:** `UserIndexRequestDTO`, `RoleIndexRequestDTO`, `ApplicationIndexRequestDTO`, `PermissionIndexRequestDTO`, `ApiKeyIndexRequestDTO`, `AuditIndexRequestDTO`, `FileIndexRequestDTO`.

**Reglas:**
- `per_page` por defecto = 20.
- Tope duro `per_page <= 100` (200 solo para `applications` — dataset chico).
- `page` es 1-indexado.
- El DTO declara `public int $per_page;` con validación `is_natural_no_zero|less_than[101]`.

### `limit` — endpoints de cap top-N

Usar `limit` cuando el endpoint retorna "los top N resultados por algún orden" y la paginación es **conceptualmente errónea** — el consumidor no puede pedir "página 2" del top N. El endpoint es un cap, no una ventana.

**Forma de la request:**
```
GET /api/v1/admin/metrics/slow-requests?threshold=500&limit=10
```

**Forma de la respuesta:**
```json
{
  "status": "success",
  "data": [ ... hasta 10 entradas ordenadas por latency desc ... ]
}
```

**Usado por:** `SlowRequestsQueryRequestDTO`.

**Reglas:**
- `limit` por defecto refleja el "top N por defecto" más útil para el endpoint (10 para slow requests).
- Tope duro `limit <= 100`.
- Sin parámetro `page`; sin metadatos de paginación en la respuesta.

## Cuándo elegir cuál

| Pregunta | Respuesta |
|---|---|
| "¿Un cliente puede significativamente pedir página 2?" | Sí → `per_page`. No → `limit`. |
| "¿La respuesta necesita un `total` para que el cliente renderice `1–20 de 312`?" | Sí → `per_page`. No → `limit`. |
| "¿La colección upstream es chica / capeada por el diseño del endpoint?" | `limit`. |
| "¿La colección upstream es arbitrariamente grande y el cliente quiere recorrerla?" | `per_page`. |

## Anti-patrones

- **No usar `limit`+`offset`.** La paginación basada en offset a través de colecciones arbitrariamente grandes tiene problemas de performance a escala (cada request recorre `N+offset` filas). Para endpoints paginados, preferir `per_page` (offset-based hoy) y migrar a paginación cursor-based cuando llegue una señal real (open item B3 de auditoría, deferido).
- **No agregar `page` a un endpoint `limit`.** O el endpoint es top-N (usar `limit` solo) o es paginado (usar `per_page` + `page`). Mezclar los dos confunde a clientes y obliga al servidor a recorrer un result set arbitrariamente profundo.
- **No cambiar un endpoint paginado a usar `limit` "por consistencia".** Eso es un cambio breaking. Quedarse en `per_page` para endpoints paginados, punto.

## Trabajo futuro

- Introducir un `BaseIndexRequestDTO` que factorice `per_page`, `page`, `search`, `sort`, `filter` de los siete Index DTOs que actualmente duplican las mismas cinco propiedades. Ticket: aún sin abrir; trigger cuando un sexto Index DTO necesite el mismo shape.
- Migrar paginación basada en offset a cursor-based para `audit_logs`, `request_logs`, y `metrics` una vez que excedan ~1M filas o el P95 de latency de listado exceda el SLO (audit B3, deferido).
