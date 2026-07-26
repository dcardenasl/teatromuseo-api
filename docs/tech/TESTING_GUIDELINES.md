# Testing Guidelines

Practical testing rules for this repository.

## 1. API Feature Tests

For HTTP endpoint tests, always extend `Tests\Support\ApiTestCase`.

Benefits:
1. Resets PHP globals between consecutive requests in one test.
2. Resets shared request service state.
3. Provides JSON response helpers.
4. Uses DB test trait defaults consistent with this project.

## 2. Dynamic Environment in Tests

When a test changes values read via `env()`, update all CI4 runtime sources:

```php
putenv('MY_VAR=value');
$_ENV['MY_VAR'] = 'value';
$_SERVER['MY_VAR'] = 'value';
```

## 3. Model Validation and Placeholders

1. If a model validation rule uses placeholders (for example `is_unique[table.field,id,{id}]`), the placeholder field (`id`) must also have its own validation rule (for example `permit_empty`).
2. Avoid entity casts forcing nullable DB fields into non-null defaults (`0`/`false`) when semantic null is required.

## 4. Integration Tests and Database Scope

1. If code opens dedicated DB connections, ensure test execution uses the `tests` group where needed.
2. If test code mutates config state, reset factories:

```php
\CodeIgniter\Config\Factories::reset('config');
```

## 5. Canonical Testing References

1. Strategy and architecture constraints: `../architecture/TESTING.md`.
2. API testing feature playbook: `../features/TESTING_API.md`.
