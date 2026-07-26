# Formato de Respuesta de la API

La API sigue un formato de respuesta estricto y predecible. Todos los endpoints de negocio devuelven JSON envuelto en una estructura estándar, orquestada a través del pipeline de `ApiResult`.

---

## El Pipeline de Respuesta

El camino desde el Servicio hasta la respuesta HTTP está estandarizado para garantizar la consistencia en todos los dominios.

1. **Servicio**: Devuelve un DTO, Entidad u `OperationResult`.
2. **Controlador**: Delega el resultado a `ApiResponse::fromResult()`.
3. **ApiResponse**: Normaliza la entrada en un Value Object **`ApiResult`**.
4. **ApiResult**: Encapsula el `body` final (array) y el `status` (int).
5. **Controlador**: Renderiza el JSON y establece el código de estado HTTP.

---

## Normalización Automática

`ApiResponse::fromResult()` es la fábrica inteligente que maneja la transformación de datos:
- **DTOs**: Convertidos recursivamente a arrays vía `convertDataToArrays()`.
- **Paginación**: Se detecta `PaginatedResponseDTO` y se envuelve en el sobre canónico de `data` + `meta`.
- **Operaciones**: Los estados de `OperationResult` se mapean a códigos semánticos (200, 201, 202).
- **Booleanos**: Mapeados a estructuras de éxito o `ApiResponse::deleted()`.

---

## Gestión Global de Errores

Las excepciones son capturadas automáticamente por `ApiController::handleException()` y procesadas por el **`ExceptionFormatter`**.

- **Producción**: Se elimina la información sensible de depuración. Se devuelve un mensaje genérico `Api.serverError`.
- **Desarrollo/Testing**: Se incluyen nombres de clases, rutas de archivos, números de línea y trazas en la clave `errors` del JSON.
- **Códigos Semánticos**: Si una excepción implementa `HasStatusCode`, se respeta su código.

---

## Estructura Principal

```json
{
  "status": "success",
  "message": "Mensaje opcional para humanos",
  "data": { /* Carga útil principal */ },
  "meta": { /* Metadatos, ej. paginación */ }
}
```

---

## Ejemplos de Contrato

### Éxito Estándar (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John"
  }
}
```

### Error (4xx/5xx)
```json
{
  "status": "error",
  "message": "La validación ha fallado",
  "errors": {
    "email": "El correo ya está registrado"
  },
  "code": 422
}
```
