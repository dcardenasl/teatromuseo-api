# Validation System

This project validates inputs at the boundary, in **Request DTOs** — _not_ in services,
controllers, or middleware. By the time a service receives a DTO instance, the data
inside it is guaranteed to satisfy the DTO's rules.

## Validation Layers

1. **DTO Validation:** Request DTOs (`app/DTO/Request/`) extend
   `dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO` and declare a `rules()` array. The base
   constructor runs CI4's `ValidationInterface` against `$data` before calling `map()`;
   if any rule fails it throws `ValidationException` (HTTP 422). Once the object exists,
   the data is valid.
2. **Model Validation:** `Model::$validationRules` on auditable models is a second
   line of defense against direct repository writes (seeders, migrations, internal
   tooling). It is not a substitute for DTO validation on user input.
3. **Business Rules:** Pure service logic — uniqueness, authorization preconditions,
   workflow state checks — lives in services and throws domain exceptions
   (`AuthorizationException`, `ConflictException`, `BadRequestException`, etc.) that
   `ApiController::handleRequest()` translates to the appropriate HTTP status.

---

## DTO Auto-Validation

`BaseRequestDTO::__construct(array $data, ?ValidationInterface $validation = null)`
runs `validate($data)` (which executes `rules()`) and then `map($data)`. The validation
service is injected automatically by `dcardenasl\Ci4ApiCore\Support\RequestDtoFactory`,
which is what `ApiController::handleRequest()` uses under the hood.

```php
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LoginRequest', required: ['email', 'password'])]
readonly class LoginRequestDTO extends BaseRequestDTO
{
    public string $email;
    public string $password;

    public function rules(): array
    {
        return [
            'email'    => 'required|valid_email|max_length[255]',
            'password' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->email    = strtolower(trim((string) $data['email']));
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return ['email' => $this->email, 'password' => $this->password];
    }
}
```

### What you get for free
- **Make illegal states unrepresentable** — services receive a typed, validated object,
  not an associative array.
- **Fail fast** — the request is rejected before any service code runs.
- **Single rule location** — `rules()` is the canonical place to look for what a
  payload accepts.
- **Consistency between HTTP, CLI, and tests** — the same DTO class enforces the same
  rules regardless of caller.

### Custom messages
Override `messages()` to return CI4-style per-rule messages keyed by `field.rule`. Keep
messages as `lang()` keys so they translate (see the architecture test
`tests/Unit/Architecture/ValidationI18nConventionsTest`, which fails on hardcoded
strings inside `ValidationException` error arrays).

```php
public function messages(): array
{
    return [
        'email' => [
            'required'    => lang('Validation.emailRequired'),
            'valid_email' => lang('Validation.emailInvalid'),
        ],
    ];
}
```

---

## Throwing validation errors manually

Some DTOs need cross-field rules or normalization that CI4 rules cannot express. Do the
check inside `map()` and throw `ValidationException` with an errors array whose values
are `lang()` calls:

```php
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

protected function map(array $data): void
{
    $rawIds = $data['ids'] ?? null;
    if (!is_array($rawIds) || $rawIds === []) {
        throw new ValidationException(
            lang('Files.bulk_ids_required'),
            ['ids' => lang('Files.bulk_ids_required')]
        );
    }
    // …
}
```

The architecture test fails the build if any `ValidationException` is thrown with a
hardcoded message inside its errors array.

---

## Common Rules Used

These CI4 native rules cover most cases:

- `required`, `permit_empty`
- `string`, `integer`, `is_natural`, `is_natural_no_zero`
- `valid_email`, `valid_email_idn`
- `min_length[N]`, `max_length[N]`
- `less_than[N]`, `greater_than[N]`
- `in_list[a,b,c]`
- `matches[other_field]`

Custom rules registered in `app/Config/Validation.php`:

- `strong_password` — enforces project password policy.

Run the test suite (`vendor/bin/phpunit tests/Unit/Architecture/`) to check that all
DTOs and validation conventions are honored — there are arch tests that scan for
violations.

---

## What this project does **not** use

- **No `InputValidationService`** — that pattern was removed in v2.0.0. If you find it
  in old docs or comments, treat it as a stale reference.
- **No `validateOrFail()` global helper** — validation runs inside `BaseRequestDTO`,
  not via a procedural helper.
- **No service-layer validation** — services trust their DTO inputs. If a service
  performs business-rule checks (e.g., uniqueness against the DB), it throws a domain
  exception, not a `ValidationException`.
