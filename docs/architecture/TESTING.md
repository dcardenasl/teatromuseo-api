# Testing Strategy & Guidelines

This document outlines the strategy, architecture, and best practices for testing within the **CI4 API Starter Kit**.

## 🧪 The Three-Layer Testing Pyramid

We follow a strict three-layer testing approach to balance execution speed, isolation, and integration coverage.

### 1. Unit Tests (Fast & Isolated)
- **Location:** `tests/Unit/`
- **Scope:** Individual classes (Services, DTOs, Libraries, Traits).
- **External Dependencies:** **MUST** be mocked. Use `createMock()`.
- **Database:** No database access.
- **Focus:** Business logic, edge cases, and type safety.
- **Example:** Testing that a `FileService` throws an exception if the file is too large.

### 2. Integration Tests (Database & Models)
- **Location:** `tests/Integration/`
- **Scope:** Interactions between the application and the database.
- **External Dependencies:** Uses **SQLite** (`tests/ci4_test.sqlite`) for speed and isolation.
- **Focus:** Query builder logic, model validation rules, searchable/filterable traits, and soft deletes.
- **Constraint:** Do not mock models here; test the real database behavior.

### 3. Feature Tests (End-to-End API)
- **Location:** `tests/Feature/`
- **Scope:** Full HTTP request/response cycle.
- **Focus:** Routing, Filters (Auth/Rate-limiting), Controller orchestration, and JSON response structure.
- **Key Helper:** Use `Tests\Support\ApiTestCase` and the `AuthTestTrait` for identity propagation.

---

## 🛠️ Testing Tools & Infrastructure

### SQLite for Database Tests
To ensure tests are fast and don't require a running MySQL instance, we use **SQLite**.
- **Configuration:** Managed in `app/Config/Database.php` under the `tests` group.
- **Persistence:** Uses `tests/ci4_test.sqlite`.
- **Preparation:** Run `php spark tests:prepare-db` to reset the test database.

### Identity Propagation (SecurityContext)
Testing authenticated routes can be tricky in Feature tests. We use two mechanisms:
1. **`ContextHolder`:** A static holder that allows injecting a `SecurityContext` directly into the current request lifecycle.
2. **`TestAuthFilter`:** During tests (`ENVIRONMENT === 'testing'`), the `jwtauth` alias is automatically mapped to `TestAuthFilter`. This filter reads `X-Test-User-Id` and `X-Test-User-Role` headers to establish identity without requiring a real JWT token.

### The `actAs()` Helper
Available via `AuthTestTrait`, it automates user creation and header setup:
```php
public function testAdminCanListUsers()
{
    $this->actAs('admin'); // Creates admin, generates token, sets headers
    $result = $this->get('/api/v1/users');
    $result->assertStatus(200);
}
```

---

## 📝 How to Create a New Test

### Step 1: Choose the Level
- Adding a new business rule? → **Unit Test**.
- Adding a new complex SQL query or filter? → **Integration Test**.
- Adding a new API endpoint? → **Feature Test**.

### Step 2: Use Generators (Optional but Recommended)
The custom CRUD generator creates placeholder tests for all three layers:
```bash
bash bin/make-crud.sh Product Catalog 'name:string:required' yes
```
Interactive alternative: `php spark make:crud Product --domain Catalog`.

### Step 3: Writing the Test
**Standard Pattern (Arrange-Act-Assert):**
```php
public function test_should_do_x() 
{
    // 1. Arrange: Setup mocks or data
    $this->actAs('user');
    
    // 2. Act: Call the service or endpoint
    $result = $this->post('/api/v1/resource', $data);
    
    // 3. Assert: Verify the outcome
    $result->assertStatus(201);
    $result->assertJSONFragment(['name' => 'Test']);
}
```

---

## ⚠️ Framework Limitations & Architecture Constraints

### 1. State Leakage in Feature Tests
CodeIgniter 4's `FeatureTestTrait` does not perfectly reset the state of some services (like `Throttler` or `Cache`) between requests. 
- **Workaround:** For rate-limiting tests, use Unit tests for the filters or clear the cache explicitly in `setUp()`.

### 2. Mocking Global Functions
CodeIgniter uses many global helper functions (`lang()`, `service()`, `env()`). These are difficult to mock in Unit tests.
- **Standard:** PHPStan is configured to ignore these "undefined" functions, but try to avoid logic that heavily depends on global state within pure services.

### 3. Controller Thinness
Controllers **MUST NOT** contain business logic. If you find yourself writing complex Feature tests to verify business logic, move that logic to a Service and write a Unit test instead.

### 4. DTO Immutability
Always test that DTOs are instantiated with valid data. Since they are `readonly`, once created, they are a "guarantee" of valid state.

---

## ✅ Quality Checklist
Before submitting a PR, ensure:
- [ ] `composer quality` passes (CS, PHPStan, i18n, Tests).
- [ ] Feature tests cover all RBAC roles (User/Admin/Superadmin).
- [ ] Unit tests for services cover both success and failure (exception) paths.
- [ ] No hardcoded IDs or emails; use `Faker` or unique generators.
