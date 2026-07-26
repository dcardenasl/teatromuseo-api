# 2. Implement Repository Pattern for Data Persistence

Date: 2026-03-03

## Status

Accepted

## Context

Initially, our `BaseCrudService` and domain services directly depended on `CodeIgniter\Model` and `QueryBuilder`. While functional, this tightly coupled our business logic to CodeIgniter's specific Active Record implementation. This made pure unit testing difficult without touching a database, and prevented us from easily swapping out storage mechanisms (like an external API or Doctrine) without rewriting the Service layer.

## Decision

We have decoupled the Services from the Models by introducing the **Repository Pattern**:
1. **RepositoryInterface:** All data persistence operations must adhere to `App\Interfaces\Core\RepositoryInterface`.
2. **BaseRepository:** A generic implementation (`App\Repositories\GenericRepository`) wraps `CodeIgniter\Model` and internalizes `QueryBuilder` logic (like filters, search, and sort).
3. **Service Layer:** Services now inject `RepositoryInterface` via constructor dependency injection. They pass generic arrays for criteria instead of manipulating a query builder instance.

## Consequences

- **Positive:** Complete isolation of the data access layer. Services are now 100% agnostic to the database framework. Unit testing can be achieved with simple in-memory repository mocks.
- **Negative:** Adds a layer of indirection. Developers must remember to expose specific custom queries through the repository interface rather than calling `$this->model->where(...)` directly inside a service.