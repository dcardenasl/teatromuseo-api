# Request Flow

This document walks through a complete HTTP request from start to finish.

---

## Complete Flow Diagram

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────┐
│ 1. ROUTING                              │
│    - Match URL to controller/method     │
│    - Assign filters                     │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 2. FILTERS (Middleware Pipeline)        │
│    Throttle → JwtAuth → RoleAuth        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLLER (Orchestration)           │
│    - `RequestDataCollector` merges      │
│      GET/POST/JSON/files cleanly        │
│    - establishSecurityContext()         │
│    - `RequestDtoFactory` creates DTO     │
│      instances with shared validator    │
│    - handleRequest() executes target    │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 4. DTO LAYER (The Shield)               │
│    - Self-validates in constructor      │
│    - Receives context-enriched payload  │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. DOMAIN SERVICE (Business Logic)      │
│    - Decomposed into Handlers/Guards    │
│    - Pure domain logic only             │
│    - Returns DTO or OperationResult     │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. RESPONSE PIPELINE                    │
│    - ApiResponse::fromResult()          │
│    - Normalizes into ApiResult          │
│    - Controller renders JSON            │
└─────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

---

## Example: Create User (POST /api/v1/users)

### 1. Controller & DTO

```php
// ApiController::handleRequest
$data = $this->collectRequestData();
$context = $this->establishSecurityContext();

// BaseRequestDTO::__construct
$this->validate($data);
$this->map($data);
```

### 2. Service Logic (Composed)

```php
public function store(UserStoreRequestDTO $request): UserResponseDTO
{
    // Delegate security
    $this->roleGuard->assertCanAssignRole(...);

    $userId = $this->model->insert($request->toArray());
    
    // Delegate secondary processes
    $this->invitationService->sendInvitation($user);

    return $this->mapToResponse($user);
}
```

### 3. Automatic Normalization

1.  `ApiResponse::fromResult()` receives `UserResponseDTO`.
2.  Converts recursively to array.
3.  Wraps in `ApiResult` with status `201`.
4.  `ApiController` renders final JSON.

---

## Error Flow

If an exception occurs:
1.  `ApiController::handleException()` catches it.
2.  `ExceptionFormatter::format()` determines environment and security.
3.  Returns an `ApiResult` with proper status and error payload.
4.  Controller renders JSON.

---

## Key Takeaways

1. **Linear flow** - Orderly transition through layers.
2. **Composition** - Services delegate specialized tasks.
3. **Fail fast** - DTOs stop bad data before logic.
4. **Centralized boundaries** - `RequestDtoFactory` + `Auditable` guards keep sanitization/validation consistent and testable; `AuditServiceInterface` is injected into every model so the trait never touches the container.
4. **Consistent responses** - `ApiResult` ensures universal format.
5. **Contextual awareness** - `SecurityContext` is injected at the HTTP boundary before DTO creation.
