# 1. Usar Arquitectura DTO-First

Fecha: 2026-03-03

## Estado

Aprobado

## Contexto

En el desarrollo moderno de APIs, pasar arreglos crudos u objetos `Request` genéricos del framework hacia la capa de servicios crea dependencias invisibles, esquemas ocultos y vacíos de validación. Los servicios quedan acoplados al contexto HTTP y entender qué datos requiere realmente un servicio se vuelve ambiguo, llevando a "spaghetti code".

## Decisión

Se aplicará estrictamente una arquitectura **DTO-First (Data Transfer Object)** en todo el starter kit:
1. **Los límites HTTP terminan en el Controller:** `ApiController` recolecta input y lo mapea inmediatamente a `BaseRequestDTO`.
2. **Inmutabilidad:** todos los DTOs deben usar `readonly class` en PHP 8.2 para evitar mutación de estado durante la ejecución.
3. **Autovalidación:** los constructores de DTO validan y sanitizan el arreglo de entrada antes de crear el objeto.
4. **Pureza de servicios:** los servicios solo aceptan DTOs (por ejemplo `UserStoreRequestDTO`) y `SecurityContext`, permaneciendo agnósticos a la lógica HTTP.

## Consecuencias

- **Positivas:** reducción significativa de "Fat Controllers" y "Fat Services". La validación de entrada queda encapsulada. El código es fuertemente tipado y autodocumentado.
- **Negativas:** requiere más boilerplate (DTOs de request y response) por endpoint, lo que puede sentirse costoso en CRUDs simples (mitigado por herramientas CLI).
