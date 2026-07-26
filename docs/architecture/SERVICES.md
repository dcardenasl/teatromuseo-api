# Service Layer & IoC

The Service layer contains all business logic and orchestrates domain operations. In this architecture, services are **organized into domains**, follow a **composition pattern**, and are decoupled from persistence via **Repositories**.

---

## Domain-Driven Organization

Services are grouped by functional domain to ensure high cohesion and prevent "God Classes".

- `app/Services/Auth/`: Login, Registration, OAuth.
- `app/Services/Tokens/`: JWT, Refresh Tokens, Revocation.
- `app/Services/Users/`: Identity and RBAC.
- `app/Services/Files/`: Storage and File Processing.
- `app/Services/System/`: Infrastructure (Audit, Email, Metrics).

---

## Service Composition Pattern

Large services are decomposed into specialized components injected via constructor. This keeps orchestrators thin and logic testable.

### Support Components:
- **Actions**: Encapsulate command flows with write-side side effects (e.g., `RegisterUserAction`).
- **Handlers**: Encapsulate multi-step logic (e.g., `GoogleAuthHandler`).
- **Guards**: Centralize security assertions (e.g., `UserRoleGuard`).
- **Mappers**: Handle entity-to-DTO transformations (e.g., `AuthUserMapper`).

---

## Repository Pattern

To ensure services are completely agnostic of the database framework, they never touch `CodeIgniter\Model` directly. Instead, they inject a **RepositoryInterface**.

- **Repositories:** Responsible for all data persistence and retrieval.
- **Interfaces:** Services depend on interfaces (e.g., `UserRepositoryInterface`) rather than concrete implementations.
- **Testability:** Repositories can be easily mocked for pure unit testing of services.

---

## Pure & Immutable Services

All new services must be **Pure**, **Stateless**, and declared as `readonly class` (PHP 8.2+).

- **Input:** Specific DTOs, SecurityContext, or Scalar types.
- **Output:** DTOs, Entities, or `OperationResult`.
- **Errors:** Thrown as custom Exceptions implementing `HasStatusCode`.
- **Immutability:** Dependencies are injected via constructor and never change.

### Example (Composition & Repository)

```php
// app/Services/Auth/AuthService.php
readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthUserMapper $userMapper,
        protected UserAccountGuard $userAccessPolicy
    ) {}

    public function login(LoginRequestDTO $request): LoginResponseDTO
    {
        // 1. Retrieval via Repository
        $user = $this->userRepository->findByEmail($request->email);
        
        // 2. Business Assertion via Guard
        $this->userAccessPolicy->assertCanAuthenticate($user);
        
        // 3. Transformation via Mapper
        return $this->userMapper->mapToResponse($user);
    }
}
```

---

## Registering Services (IoC)

All services and their dependencies must be registered in `app/Config/Services.php` (or a domain-specific service provider trait).

```php
// app/Config/Services.php
public static function authService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('authService');
    }
    
    return new \App\Services\Auth\AuthService(
        static::userRepository(), // Inject Repository instead of Model
        new \App\Services\Auth\Support\AuthUserMapper(),
        new \App\Services\Users\UserAccountGuard()
    );
}
```

---

## Benefits

1. **Isolation:** Business logic is 100% independent of CodeIgniter's Active Record.
2. **Mockability:** Test orchestrators without a database by mocking the repository interface.
3. **Discoverability:** Clear domain folders and explicit interfaces.
