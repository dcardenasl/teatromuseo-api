# The Architecture Layers

This document explains each layer in detail: Controller, DTO, Service, Repository, Model, and Entity.

---

## Controller Layer

**Location:** `app/Controllers/Api/V1/`

**Responsibility:** Act as a thin orchestrator between the HTTP Request and the Service Layer.

### ApiController (Base Class)

All API controllers extend `ApiController.php`. It provides a declarative `handleRequest` method that automates:
1. **RequestDataCollector:** Centralizes data merging from all sources (GET, POST, JSON, Files) using a shared service.
2. **RequestDtoFactory:** Instantiates the requested DTO class, injecting a shared `ValidationInterface` to ensure consistent rules without static calls.
3. Exception handling and transformation to JSON via specialized formatters.
4. Response normalization (success/error wrapping and `data` keying).

### Pattern: Declarative Orchestration

```php
public function create(): ResponseInterface
{
    // The controller declares WHAT to execute and WHICH DTO validates input.
    return $this->handleRequest('store', UserStoreRequestDTO::class);
}
```

---

## DTO Layer (Data Transfer Objects)

**Location:** `app/DTO/`

**Responsibility:** Data Integrity, Type Safety, and Contract Stability.

### Request DTOs (Input Guardians)
- **Base Class:** Extend `BaseRequestDTO`.
- **Constructor Injection:** Receives a `ValidationInterface` instance from the `RequestDtoFactory`.
- **Self-validation:** Validation happens in the constructor via the `rules()` method. 
- **Safety:** If a DTO object exists in memory, the data is guaranteed to be valid.
- **Immutability:** PHP 8.2 `readonly` classes.

---

## Service Layer

**Location:** `app/Services/`

**Responsibility:** Business logic, transactional integrity, and domain rules.

### Pure & Stateless Services

Services must be agnostic of HTTP, JSON, or direct database models. They use **Repositories** for persistence.

```php
readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function store(ProductRequestDTO $request): ProductResponseDTO
    {
        // 1. Logic
        // 2. Persistence via Repository
        $product = $this->productRepository->insert($request->toArray());
        // 3. Return DTO
    }
}
```

---

## Repository Layer

**Location:** `app/Repositories/`

**Responsibility:** Data persistence, query building, and database abstraction.

- **Isolation:** Repositories wrap CodeIgniter Models and handle all `QueryBuilder` logic.
- **Criteria:** Services pass generic criteria arrays to repositories for complex queries.
- **Stateless:** Repositories ensure that query builders are reset between calls to prevent state leakage.

---

## Model & Entity Layers

**Location:** `app/Models/` & `app/Entities/`

**Responsibility:** Data representation.

- **Models:** Inherit from CodeIgniter's Model. They are consumed ONLY by Repositories.
- **Auditable:** Models use the `Auditable` trait, which receives an injected `AuditServiceInterface` via the Service Container.
- **Entities:** Represent row data. `UserEntity` explicitly sanitizes sensitive fields in its `toArray()` method.

---

## Summary

| Layer | Responsibility | Pattern |
|-------|----------------|---------|
| **Controller** | Orchestration | `RequestDataCollector` + `RequestDtoFactory` |
| **DTO** | Integrity | Validated `readonly` class |
| **Service** | Logic | Pure, Stateless, `readonly` |
| **Repository** | Abstraction | Decouples Service from Model |
| **Model** | Persistence | Auditable Query Builder |
| **Entity** | Representation | Strongly Typed Row with sanitization |

**Flow:**
```
Request → Controller (Orchestrator) → RequestDTO (Guardian) → Service (Logic) → Repository (Storage) → Model → Entity → ResponseDTO (Contract) → JSON
```
