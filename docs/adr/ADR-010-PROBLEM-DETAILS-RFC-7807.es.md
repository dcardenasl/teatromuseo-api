# ADR-010: RFC 7807 Problem Details opcional para respuestas de error

## Estado
Aceptado (auditoría B7.4, 2026-05-06)

## Contexto

El kit envía por defecto el siguiente sobre de error:

```json
{ "status": "error", "message": "...", "errors": { ... }, "code": 422 }
```

Funciona, todas las apps consumidoras lo reconocen, y `ApiResponse::error()` está bien testeada. Pero no es el contrato que el tooling downstream espera:

- **API gateways** (Cloudflare API Shield, AWS API Gateway, Kong, Apigee) parsean `type` y `status` de bodies RFC 7807 para categorizar errores y enrutar métricas por clase de problema.
- **Generadores de código OpenAPI / Swagger** templatean contra shapes `ProblemDetails` cuando `application/problem+json` se declara en `produces`.
- **Contratos de integración enterprise** frecuentemente mandan RFC 7807 por nombre.

No queremos romper a todos los consumidores actuales del sobre default, pero queremos poder responder "sí" cuando un cliente RFC-7807 lo pida.

## Decisión

1. **Agregar `ApiResponse::problemDetails()` como builder aditivo.** Builder puro — sin I/O, sin opiniones. Construye un body que cumple RFC 7807:

   ```json
   {
     "type": "https://example.com/errors/validation-failed",
     "title": "Validation failed",
     "status": 422,
     "detail": "...",
     "instance": "/api/v1/users",
     "errors": { "email": "required" }
   }
   ```

   - `type` por defecto es `"about:blank"` por RFC 7807 §4.2 cuando no se provee URI específico. Los callers DEBERÍAN proveer un URI estable (típicamente la página de docs que explica la clase de error) para que los clientes puedan reconocerlo y bifurcar por él.
   - `errors` es un campo aditivo no-RFC que preserva el mapa de validación por campo. RFC 7807 explícitamente permite miembros extension (§3.2), así que es compliant.

2. **Agregar `ApiResponse::negotiateError()` como el entry point opt-in.** Toma un valor del header `Accept` y retorna:
   - El sobre legacy (`status` / `message` / `errors` / `code`) bajo `Content-Type: application/json`, O
   - El sobre 7807 bajo `Content-Type: application/problem+json`,

   según si el header Accept menciona explícitamente `application/problem+json`. Retorna un pequeño array `{body, content_type}` para que el controller que llama pueda fijar el `Content-Type` correcto en la respuesta.

3. **Sin globals, sin negociación de contenido implícita en el framework.** Los call sites existentes se quedan en `error()`. Los controllers (o filters futuros) opt-in deliberadamente cambiando a `negotiateError()` cuando quieran el comportamiento de doble formato. Esto mantiene el blast radius del cambio en cero para los consumidores actuales.

4. **Helper `clientPrefersProblemJson()`** para cuando el código que llama quiere tomar decisiones de routing por sí mismo (ej. fijar Content-Type antes de construir el body por una vía distinta).

## Consecuencias

### Positivas
- Los integradores RFC-7807 obtienen un contrato de primera clase enviando `Accept: application/problem+json`.
- Los consumidores internos existentes (admin starter, CLI tools, fixtures de dev) no ven cambio.
- Los controllers futuros pueden adoptar `negotiateError()` selectivamente — endpoints de alto riesgo primero.
- `problemDetails()` también es útil por sí solo (ej. para endpoints explícitamente 7807 que no necesitan negociar).

### Negativas
- Dos shapes de error aumentan la superficie de testing: cada path de error que adopte negociación necesita un test por cada shape.
- `clientPrefersProblemJson()` es un parser q-aware mínimo, NO una implementación completa de RFC 7231 §5.3.2. Headers Accept patológicos (ej. `application/problem+json;q=0`) son ignorados — el parser cambia ante cualquier mención no vacía. Clientes prácticos no envían tales headers; si alguno real surge, el parser es chico para upgradear.

### Neutras
- El spec 7807 manda `Content-Type: application/problem+json` en respuestas con ese body shape. El caller opt-in es responsable de fijar ese header — `negotiateError()` solo retorna el body y el content type recomendado como hint.

## Punteros de implementación

- Builder + negotiator: `vendor/dcardenasl/ci4-api-core/src/Http/ApiResponse.php` — métodos `problemDetails`, `negotiateError`, `clientPrefersProblemJson`.
- Tests: `tests/Unit/Libraries/ApiResponseTest.php` — 6 casos cubriendo los nuevos builders.

## Trabajo futuro

- Extender `ApiController::handleRequest()` (o un wrapper delgado) para opt-in cada controller en negociación por defecto. Fuera de alcance para v0.x de esta política — el opt-in por ruta mantiene el blast radius chico hasta que veamos demanda real del consumidor.
- Introducir un esquema URI estable para `type` (ej. `https://api.example.com/errors/validation-failed`) y una página docs por tipo que documente el contrato. Mejor hecho junto al corte de v2.
- Adoptar `application/problem+xml` si algún consumidor lo pide materialmente. No está en el radar.
