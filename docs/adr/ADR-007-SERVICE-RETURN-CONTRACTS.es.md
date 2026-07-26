# ADR-007: Contratos de Retorno de Servicios (DTO vs OperationResult)

## Estado
Aceptado

## Contexto

Los tipos de retorno de servicios deben ser predecibles para evitar fugas de preocupaciones HTTP y mantener estable la normalización en controllers.

## Decisión

1. Operaciones de lectura/consulta retornan DTOs (`DataTransferObjectInterface`).
2. Operaciones tipo comando retornan `OperationResult` cuando importa la semántica de resultado (success/accepted/error + message/errors).
3. El contrato CRUD se mantiene explícito:
- `index/show/store/update` -> DTO
- `destroy` -> `bool`
4. El formateo de respuesta se centraliza en `ApiController` + `ApiResponse::fromResult`.

## Consecuencias

### Positivas
- Contratos de servicio predecibles y pipeline de normalización API más simple.
- Mejor compatibilidad para módulos generados y tests de arquitectura.
- Menor acoplamiento accidental entre lógica de negocio y semántica de transporte.

### Trade-offs
- Requiere disciplina al introducir nuevos métodos tipo comando.
- Algunos flujos requieren conversión explícita de salida entidad/modelo hacia DTO/OperationResult.
