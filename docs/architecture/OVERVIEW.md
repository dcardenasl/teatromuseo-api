# Architecture Overview

## Executive Summary

This project is an **enterprise-grade REST API** built on CodeIgniter 4, following a strictly layered architecture with clear separation of responsibilities:

```
Controller → Service → Model → Entity
```

### Key Features

- **Layered Architecture** - Clear separation between presentation, business logic, and data access
- **Dependency Injection** - IoC container for decoupling and testability
- **Multi-Level Validation** - Input layer, model layer, business rules
- **Stateless JWT Authentication** - With refresh tokens and revocation support
- **Full Internationalization** - Multi-language support (en/es)
- **Structured Exception System** - Automatic mapping to HTTP status codes
- **Advanced Query Builder** - Filtering, FULLTEXT search, and pagination

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────────────┐
│                      PRESENTATION LAYER                             │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        FILTERS                                 │  │
│  │  CorsFilter → ThrottleFilter → JwtAuthFilter → RoleAuthFilter │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                      CONTROLLERS                               │  │
│  │   ApiController (Base) → UserController, AuthController        │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                      BUSINESS LAYER                                 │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                   INPUT VALIDATION                             │  │
│  │  AuthValidation, UserValidation, FileValidation                │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       SERVICES                                 │  │
│  │  UserService, AuthService, JwtService, FileService             │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                      LIBRARIES                                 │  │
│  │  ApiResponse, QueryBuilder, StorageManager, QueueManager       │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                        DATA LAYER                                   │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        MODELS                                  │  │
│  │  UserModel (Filterable, Searchable), FileModel, AuditLogModel │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       ENTITIES                                 │  │
│  │  UserEntity, FileEntity (with computed properties)             │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                     INFRASTRUCTURE                                  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Database (MySQL) | Cache | Queue | Storage (Local/S3)        │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## SOLID Principles

| Principle | Implementation |
|-----------|----------------|
| **Single Responsibility** | Each class has one reason to change |
| **Open/Closed** | Extensible via inheritance and traits, closed for modification |
| **Liskov Substitution** | Services implement interchangeable interfaces |
| **Interface Segregation** | Domain-specific interfaces |
| **Dependency Inversion** | Depend on abstractions (interfaces) not implementations |

---

## Layer Responsibilities

### Presentation Layer
**Controllers & Filters**

- Handle HTTP requests and responses
- Apply middleware (auth, throttling, CORS)
- Collect and sanitize input
- Delegate to services
- Return formatted JSON responses

**Rules:**
- NO business logic in controllers
- ONLY HTTP-related code
- Thin controllers, fat services

### Business Layer
**Services & Libraries**

- Contain all business logic
- Orchestrate operations across models
- Validate business rules
- Transform data
- Throw domain exceptions
- Format API responses

**Rules:**
- Services return arrays (via ApiResponse)
- Services throw exceptions (never return error arrays)
- Services implement interfaces for testability

### Data Layer
**Models & Entities**

- **Models**: Database operations via query builder
- **Entities**: Data representation and transformation

**Rules:**
- NO business logic in models
- NO raw SQL (use query builder)
- Models return entities
- Entities hide sensitive fields

### Infrastructure Layer
**Database, Cache, Queue, Storage**

- External systems and drivers
- Configured via environment variables
- Swappable implementations (S3 vs local storage)

---

## Request/Response Cycle

Every API request follows this path:

```
1. HTTP Request
   ↓
2. Routing (matches controller + method)
   ↓
3. Filters (CORS → Throttle → JwtAuth → RoleAuth)
   ↓
4. Controller (collect data, sanitize, delegate)
   ↓
5. Service (validate, process, format response)
   ↓
6. Model (query database)
   ↓
7. Entity (cast types, hide fields)
   ↓
8. Service (format with ApiResponse)
   ↓
9. Controller (set HTTP status, return JSON)
   ↓
10. HTTP Response
```

**Example timing (typical request):**
- Filters: ~5ms
- Controller: ~2ms
- Service: ~10-50ms (business logic)
- Model: ~5-20ms (database)
- Total: ~25-80ms

---

## Directory Structure

```
app/
├── Config/
│   ├── Routes.php              # Route definitions
│   ├── Services.php            # IoC container
│   └── Filters.php             # Filter aliases
│
├── Controllers/
│   ├── ApiController.php       # Base controller
│   └── Api/V1/                 # API v1 controllers
│
├── Services/                   # Business logic
├── Interfaces/                 # Service contracts
├── Models/                     # Database access
├── Entities/                   # Data objects
├── Filters/                    # Middleware
├── Exceptions/                 # Custom exceptions
├── Libraries/                  # Shared utilities
├── Validations/                # Input validation
├── Traits/                     # Reusable behaviors
└── Language/                   # i18n translations
```

---

## Design Decisions

### Why Layered Architecture?
✅ **Maintainability** - Clear boundaries, easy to locate code
✅ **Testability** - Each layer tested independently
✅ **Scalability** - Layers can be optimized or replaced
✅ **Team Collaboration** - Different developers work on different layers

### Why Services + Interfaces?
✅ **Dependency Injection** - Easy to swap implementations
✅ **Testability** - Mock services in tests
✅ **Reusability** - Services can be used by multiple controllers

### Why Entities?
✅ **Type Safety** - Automatic type casting
✅ **Security** - Hide sensitive fields automatically
✅ **Encapsulation** - Computed properties and domain logic

### Why Custom Exceptions?
✅ **Consistency** - Automatic HTTP status code mapping
✅ **Clarity** - Intent is clear from exception type
✅ **Centralized Handling** - One place to format error responses

---

## Next Steps

**Understand the layers:**
- Read [LAYERS.md](LAYERS.md) for detailed explanation of each layer

**See it in action:**
- Read [REQUEST_FLOW.md](REQUEST_FLOW.md) for complete request walkthrough

**Learn specific systems:**
- [FILTERS.md](FILTERS.md) - Middleware system
- [VALIDATION.md](VALIDATION.md) - Multi-level validation
- [EXCEPTIONS.md](EXCEPTIONS.md) - Exception handling
- [AUTHENTICATION.md](AUTHENTICATION.md) - JWT auth flow

**Full roadmap:**
- See [README.md](README.md) for learning paths
