# Design Patterns

## Core Patterns

### Data Transfer Object (DTO)
Used for all data transfer between Controllers and Services.
- **Implementation:** PHP 8.2 `readonly` classes.
- **Benefits:** Inmutability, strict typing, and auto-validation in constructors.

### Pure Service Pattern
Services are decoupled from HTTP/API concerns.
- **Implementation:** Return DTOs or Entities, throw exceptions for errors.
- **Benefits:** Testability and reuse across different interfaces (Web, CLI, API).

### Normalizer / Serializer Pattern
Automatic conversion of complex objects to JSON-ready arrays.
- **Implementation:** `ApiController::respond` calling `ApiResponse::convertDataToArrays`.
- **Benefits:** Contract stability and automatic property mapping.

### Living Documentation Pattern
OpenAPI annotations live where the data is defined.
- **Implementation:** `#[OA\Property]` attributes in DTO classes.
- **Benefits:** Code and documentation are always in sync.

## Structural Patterns

### Template Method Pattern (ApiController)
Defines the request handling algorithm skeleton (`handleRequest`), while subclasses provide domain-specific services.

### Strategy Pattern (Storage Drivers)
Allows interchangeable storage backends (Local, AWS S3) via `StorageManager`.

### Chain of Responsibility (Filters)
Requests pass through a chain of filters (Auth, Throttle, Locale) before reaching the controller.

### Facade Pattern (AuthTokenService)
Provides a simplified interface to complex token management subsystems.

### Observer Pattern (Events)
Used for decoupling side-effects like auditing and email sending from main business flows.
