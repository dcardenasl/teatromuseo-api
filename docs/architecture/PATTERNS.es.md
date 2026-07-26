# Patrones de Diseño

## Patrones Core

### Data Transfer Object (DTO)
Utilizado para todo el transporte de datos entre Controllers y Services.
- **Implementación:** Clases `readonly` de PHP 8.2.
- **Beneficios:** Inmutabilidad, tipado estricto y auto-validación en constructores.

### Patrón de Servicio Puro
Los servicios están desacoplados de las preocupaciones de HTTP/API.
- **Implementación:** Retornan DTOs o Entidades, lanzan excepciones para errores.
- **Beneficios:** Testabilidad y reutilización en diferentes interfaces (Web, CLI, API).

### Patrón Normalizador / Serializador
Conversión automática de objetos complejos a arreglos listos para JSON.
- **Implementación:** `ApiController::respond` llamando a `ApiResponse::convertDataToArrays`.
- **Beneficios:** Estabilidad del contrato y mapeo automático de propiedades.

### Patrón de Documentación Viva
Las anotaciones de OpenAPI viven donde se definen los datos.
- **Implementación:** Atributos `#[OA\Property]` en las clases DTO.
- **Beneficios:** El código y la documentación están siempre sincronizados.

## Patrones Estructurales

### Template Method Pattern (ApiController)
Define el esqueleto del algoritmo de manejo de peticiones (`handleRequest`), mientras que las subclases proporcionan los servicios específicos del dominio.

### Strategy Pattern (Storage Drivers)
Permite backends de almacenamiento intercambiables (Local, AWS S3) a través de `StorageManager`.

### Chain of Responsibility (Filters)
Las peticiones pasan por una cadena de filtros (Auth, Throttle, Locale) antes de llegar al controlador.

### Facade Pattern (AuthTokenService)
Proporciona una interfaz simplificada para subsistemas complejos de gestión de tokens.

### Observer Pattern (Events)
Utilizado para desacoplar efectos secundarios como auditoría y envío de correos de los flujos de negocio principales.
