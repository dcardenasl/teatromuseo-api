# Template Quality Gates

Every PR must pass these gates before merge.

## 1. Static and Style Gates

1. `composer cs-check`
2. `composer phpstan`
3. `composer arch-drift`
4. `php scripts/i18n-check.php`
5. `php scripts/docs-i18n-parity-check.php`

## 2. Test Gates

1. `php spark tests:prepare-db`
2. `vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox`
3. Prefer `composer quality` to run the full gate chain in order.

## 3. Architecture Gates

These architecture tests must remain green:

1. `tests/Unit/Architecture/ApiControllerConventionsTest.php`
2. `tests/Unit/Architecture/ControllerConventionsTest.php`
3. `tests/Unit/Architecture/ServiceOperationResultContractsTest.php`
4. `tests/Unit/Architecture/CrudIndexContractsTest.php`
5. `tests/Unit/Architecture/ControllerDtoRequestContractsTest.php`
6. `tests/Unit/Architecture/RuntimeInstantiationConventionsTest.php`
7. `tests/Unit/Architecture/FilterConventionsTest.php`
8. `tests/Unit/Architecture/ControllerRouteCoverageTest.php`
9. `tests/Unit/Architecture/ServicePurityConventionsTest.php`

## 4. Contract Expectations

1. Controllers use `handleRequest(...)` with DTOs.
2. Services do not build HTTP responses.
3. `CrudServiceContract::index()` returns `DataTransferObjectInterface`.
4. Command workflows return `OperationResult`.
5. i18n parity exists for new language keys (`en` + `es`).

## 5. Release Readiness Checklist

1. `git status` clean after tests.
2. Docs updated when contracts or conventions change.
3. New module scaffolding outputs files compliant with contract guardrails.
4. New CRUD modules follow `docs/template/CRUD_FROM_ZERO.md` (including scaffold validation + explicit migration step).
