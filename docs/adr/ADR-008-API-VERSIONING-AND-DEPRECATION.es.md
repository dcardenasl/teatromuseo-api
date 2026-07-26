# ADR-008: Política de versionado y deprecación de la API

## Estado
Aceptado (auditoría B7.2, 2026-05-06)

## Contexto

La API se ha desplegado siempre detrás del prefijo `/api/v1/` pero sin contrato formal sobre:

- Cuánto tiempo se soporta v1.
- Cómo (y cuándo) los clientes se enteran de que v1 entró en estado deprecado o sunset.
- Cómo descubrir programáticamente la versión actual y su sucesora.

Sin esto, los clientes integrados contra v1 no pueden planificar migraciones, y los CDN / API gateways (Cloudflare, AWS API Gateway, Kong) no pueden enrutar automáticamente según el ciclo de vida.

## Decisión

1. **Los metadatos de versión viven en `Config\Api::$apiVersions`.** Fuente única de verdad, parametrizable por entorno. Cada entrada incluye:
   - `status`: `'current' | 'deprecated' | 'sunset'`
   - `deprecated_at`: fecha ISO 8601 en que entró en deprecación, o `null`
   - `sunset_at`: fecha ISO 8601 en que la versión deja de aceptar tráfico, o `null`
   - `successor`: la versión que la reemplaza (`'v2'`, etc.) o `null`

2. **Señalización por respuesta vía `DeprecationHeadersFilter`.** El filtro corre en `globals.after`, inspecciona el path de la request por `/api/<version>/`, y emite:
   - `Deprecation: <fecha ISO 8601>` (borrador IETF / familia RFC 8594).
   - `Sunset: <fecha ISO 8601>` (RFC 8594).
   - `Link: </api/<successor>>; rel="successor-version"` (RFC 5988) cuando hay sucesor.

3. **Descubrimiento masivo vía `GET /api/versions`** (sin prefijo de versión — es un meta-endpoint). Devuelve:
   ```json
   {
     "current": "v1",
     "versions": [
       { "version": "v1", "status": "current", "deprecated_at": null, "sunset_at": null, "successor": null }
     ]
   }
   ```
   Público, sin autenticación. Contrato estable que los clientes pueden poll-ear sin autenticarse y que el tooling automatizado consume para renderizar matrices de compatibilidad.

4. **SLA por defecto del ciclo de vida** (van a runbooks de despliegue, no al código):
   - **Soporte activo:** 18 meses desde el GA de la versión.
   - **Aviso de deprecación:** 6 meses mínimo antes del sunset.
   - **Sunset:** la versión se elimina por completo; las requests devuelven `410 Gone`.

5. **Los cambios breaking van a una nueva versión, nunca a v1.** Una vez que v1 está GA (post-1.0.0 del kit), ningún cambio incompatible a endpoints existentes de v1. Cambios aditivos (nuevos campos, query params opcionales) sí son válidos dentro de v1.

## Consecuencias

### Positivas
- Los clientes pueden planificar migraciones desde su CI/CD haciendo poll a `/api/versions` semanalmente.
- Los API gateways pueden enrutar automáticamente a la versión viva.
- La aplicación del sunset queda operacionalmente clara (`410 Gone` después de la fecha).
- Un futuro GA de v2 no rompe el tráfico de v1 a mitad de vuelo.

### Negativas
- Suma una fila obligatoria en `Config\Api::$apiVersions` cada vez que se corta una nueva versión.
- Carga de mantenimiento: una versión efectivamente deprecada todavía exige mantener su grupo de rutas `v1/*` y sus tests vivos hasta el sunset.
- Los clientes que ignoren los headers Deprecation/Sunset no tienen segunda oportunidad cuando comienza a responder 410.

### Neutras
- No hay enforcement automático de la regla "el sucesor debe ser `v(n+1)`": es política, no constraint. El esquema permite saltar versiones si un despliegue rebrandea intencionalmente.

## Punteros de implementación

- **Config:** `app/Config/Api.php` — array `$apiVersions`.
- **Filtro:** `vendor/dcardenasl/ci4-api-core/src/Http/Filters/DeprecationHeadersFilter.php` — alias `deprecationheaders`, cableado en `globals.after` (después de `secureheaders`, antes de `requestLogging`).
- **Endpoint:** `app/Config/Routes.php` — closure para `GET /api/versions` que lee de `Config\Api`.
- **Tests:** `tests/Unit/Filters/DeprecationHeadersFilterTest.php` (matriz de comportamiento del filtro), `tests/Feature/Controllers/ApiVersionsEndpointTest.php` (contrato del endpoint).

## Trabajo futuro

- Cuando v1 entre en deprecación, escribir el runbook de "cortar una nueva versión de API" (B11.2, aún no redactado — ver `docs/runbooks/` para los runbooks numerados existentes) con el procedimiento: duplicación de grupo de rutas → migración de code paths → nota en CHANGELOG → actualización de headers → eliminación al sunset.
- Considerar exponer también un header `Warning` (RFC 7234 §5.5) para razones de deprecación libres en texto. Fuera de alcance para v0.x de esta política.
