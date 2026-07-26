# Audit of `bin/make-crud.sh` — 2026-04-29

> Plan executed: 14 scenarios (C01–C14) on a disposable copy of the starter at `/tmp/ci4-audit/audit-kit-api/` with an isolated MySQL on port 33077. Raw evidence in `/tmp/ci4-audit/_audit/traces/` (not versioned). This audit is **diagnosis only** — fixes are tracked in a follow-up commit.

## Executive summary

| ID  | Scenario                              | Result               | Severity  |
|-----|---------------------------------------|----------------------|-----------|
| C01 | minimal happy path                    | ✅ PASS              | —         |
| C02 | multi-field (string/decimal/text/bool/unique) | ✅ PASS    | —         |
| C03 | valid FK + filterable                 | ✅ PASS              | P2        |
| C04 | broken FK (`fk:nonexistent_table`)    | ✅ PASS (abort pre-write) ~~⚠ zombie files~~ | ~~P1~~ **FIXED** |
| C05 | `soft-delete=no`                      | ✅ PASS              | —         |
| C06 | custom route slug                     | ✅ PASS              | —         |
| C07 | acronym `APIKey` in `Security`        | ✅ PASS (warns + aborts collision) ~~❌ FAIL~~ | ~~P0~~ **FIXED** |
| C08 | reserved field `order`                | ✅ clean rejection   | —         |
| C09 | idempotence (second run)              | ✅ clean rejection   | —         |
| C10 | second resource, same domain          | ✅ PASS              | P2        |
| C11 | missing arguments                     | ✅ clean rejection   | —         |
| C12 | soft-delete `maybe`                   | ✅ clean rejection   | —         |
| C13 | `php spark make:crud` in non-TTY (direct call) | ❌ TypeError + exit 0 (wrapper required) | **P1** (was P2) |
| C14 | `module:check` after deletion         | ✅ correctly detected | —         |

**Severity counts (original):** P0 = 1 · P1 = 1 · P2 = 4 · no severity = 9.
**Severity counts (post re-verification 2026-04-30):** P1 = 1 (C13, re-classified from P2) · P2 = 3 · no severity = 11 · FIXED = 3 (C04, C07, C02-bool).

## What works well

1. **Atomic generation.** The happy path produces ~17 files (DTO×4, Service×2, Controller + OpenAPI, Model + Entity, Migration, Lang×2, Tests×3) in a single pass, all syntactically valid. Verified in C01, C02. Implementation: `app/Commands/MakeCrud.php:40-115` orchestrates `ScaffoldingOrchestrator` (`app/Support/Scaffolding/ScaffoldingOrchestrator.php`).
2. **Idempotence and rollback on conflict.** A second run of the same resource aborts before writing anything, with a message listing the conflicting files and exit code 1. C09 confirms zero working-tree mutations after rejection. Hook: `ScaffoldingOrchestrator.php:94-116` (`validateFilesDoNotExist`) + rollback at `:63-92`.
3. **Field-name pre-validation.** `FieldNameValidator` (`app/Support/Scaffolding/FieldNameValidator.php`) rejects PHP/MySQL reserved words and collisions with engine-managed columns (`id`, `created_at`, …) with clear messages. C08 (`order` MySQL keyword) → exit 1, message *"Pick a more specific name (e.g. order_number)"*, zero files created.
4. **Idempotent wiring for multiple resources.** Adding a second resource to the same domain reuses the trait and the routes file without duplicating `use`/`require`. C10 verified two services `productService()` + `categoryService()` and two route blocks in `app/Config/Routes/v1/catalog.php`.
5. **Soft-delete faithfully respected.** When `no` is passed, the migration omits `deleted_at`, the model sets `useSoftDeletes = false`, and the Entity excludes the column from `$dates`. C05 confirmed (verified with `grep -c 'deleted_at' migration | grep useSoftDeletes`).
6. **Reliable `module:check`.** `app/Commands/ModuleCheck.php:24-109` validates 13 files, placeholders, service wiring, and routes. C14 demonstrated it detects exactly the deleted file and returns exit 1 with the absolute path.
7. **Wrapper protects from the *non-TTY hang*.** `bin/make-crud.sh:91-107` captures the full spark output to a tempfile, filters only `CREATED|WIRING|✅` on success, and dumps everything on failure. C13 confirms that the direct call without quotes (shell-expanded pipe) dies, while the wrapper resolves quoting correctly.
8. **Auto-format.** Step 2 runs `composer cs-fix` (`bin/make-crud.sh:111`) — generated files pass the pre-commit hook on first try. Verified in every PASS scenario: none required manual reformatting.
9. **Architectural test enforcement.** `tests/Unit/Architecture/MakeCrudScaffoldConventionsTest.php` already validates scaffold conventions (BaseAuditableModel, standard route filters, `public function rules()` in DTOs). Good pattern to extend with regressions for this audit's findings.

## What works poorly

### 🔴 P0 · Acronyms in resource name collide with starter or produce unusable tables

**Reproduction (C07):**
```bash
bash bin/make-crud.sh APIKey Security 'name:string:required' yes
# → exit 1 on macOS (case-insensitive FS):
#   Scaffolding aborted to prevent overwriting existing work...
#   - app/Entities/APIKeyEntity.php
#   - app/Models/APIKeyModel.php
#   - app/Language/en/APIKeys.php
```

**Root cause:** `StringHelper::studly()` (`app/Support/Scaffolding/StringHelper.php:16-36`) preserves the internal casing of already-alphanumeric identifiers (line 27-29 — explicit comment about avoiding `Schoolcategory`). For `APIKey` this produces files `APIKeyEntity.php`, `APIKeyModel.php`, etc. The starter ships `app/Models/ApiKeyModel.php` and friends. On case-insensitive systems (HFS+/APFS, NTFS), both names resolve to the same path and the conflict detector flags them as pre-existing. **On Linux ext4/btrfs (case-sensitive), the scaffold WOULD NOT detect the collision and would silently overwrite parts of the starter's artifacts** — a considerably worse scenario.

**Coupled underlying bug:** Even if the file didn't collide, `StringHelper::toSnakeCase()` (`StringHelper.php:59-62`) uses the regex `(?<!^)[A-Z]` and would produce table `a_p_i_keys` for `APIKey`. The function `getResourcePluralSnakeCase()` (`ResourceSchema.php:47-49`) consumes that logic directly. Same class of bug as M07 in the admin audit (a_p_i_keys/ view, lang `'a p i key'`).

**Impact:** Any resource with two or more consecutive uppercase letters (HTTPRequest, IPAddress, JSONPayload, OAuthToken, ID-related…) generates unusable artifacts or stomps starter code without warning.

---

### 🟠 P1 · FK with non-existent target table: scaffold ✅ + migrate "OK" yet silently failing

**Reproduction (C04):**
```bash
bash bin/make-crud.sh Bad Sales 'ghost_id:fk:nonexistent_table:required' yes
# → exit 0, 17 files created
php spark migrate
# → prints DatabaseException 'Failed to open the referenced table nonexistent_table'
# → SPARK PROCESS EXIT CODE = 0  (no failure in CI!)
```

**Root cause #1 — scaffold doesn't validate FK:** `FieldStringParser` (`app/Support/Scaffolding/FieldStringParser.php`) accepts any identifier after `fk:`. `TypeMapper` (`app/Support/Scaffolding/TypeMapper.php:115-147`) generates `is_not_unique[<table>.id]` and the migration adds `addForeignKey('ghost_id', 'nonexistent_table', 'id', 'CASCADE', 'CASCADE')` without verifying that the referent exists. Result: 17 zombie files left in the repo and the only signal is the migration exception (which many devs read as a warning).

**Root cause #2 — `spark migrate` doesn't return exit ≠ 0 on failure:** complementary framework observation (CI4 4.7.0). Example:
```
$ php spark migrate
[CodeIgniter\Database\Exceptions\DatabaseException]
Failed to open the referenced table 'nonexistent_table'
$ echo $?
0
```
Identical re-run. A CI pipeline running `php spark migrate && next-step` doesn't notice. This **isn't** a scaffold bug, but it amplifies the damage when combined with root cause #1.

**Root cause #3 — there's no `make:crud:remove`:** the dev is left with 17 orphan files + entries in `Services.php` + injected block in routes. Full manual cleanup takes ~10 steps (see "Manual cleanup" in `docs/template/CRUD_FROM_ZERO.md`).

---

### 🟡 P2 · Hardcoded `ON DELETE CASCADE` for FKs

`MigrationGenerator` (via `TypeMapper`) always emits `addForeignKey(col, table, 'id', 'CASCADE', 'CASCADE')` (verified in C03: `app/Database/Migrations/*CreateOrderItemsTable.php`). There is no way to request `RESTRICT` or `SET NULL` from the `fk:table[:nullable]` syntax. For many domains (orders→customers, audit_logs→users) cascade-delete is destructive. Current workaround: edit the generated migration before applying it.

---

### 🟡 P2 · Scaffolding output marks routes "CREATED" when they are UPDATED

Verification (C10): when adding a second resource to the same domain, stdout says:
```
CREATED: /private/tmp/ci4-audit/audit-kit-api/app/Config/Routes/v1/catalog.php
```
…even though the file already existed. The `RouteGenerator` performs an *upsert* (injects the block), not a *create*. The message is misleading for devs scanning the logs to figure out "which new files do I need to git add".

---

### 🟡 P2 · `bool` without `:required` or `:nullable` is implicitly *permit_empty + default false*

C02 generated:
```php
public bool $is_paid;                               // non-nullable type
'is_paid' => 'permit_empty|boolean_like',          // allows missing
$this->is_paid = (bool) ($data['is_paid'] ?? false); // silent default
```
Ambiguous behavior: the client can omit `is_paid` and gets `false` without knowing it, while the OpenAPI signature suggests the field is a plain `boolean`. Better option: reject `:bool` fields without an explicit modifier, or document the default rule.

---

### 🟡 P2 · Direct call to `php spark make:crud` with unquoted pipes dies with broken pipe

Confirmed in C13: the shell consumes `|searchable` as the start of a pipeline, `searchable` doesn't exist (exit 127), and spark closes with `fwrite(): Broken pipe`. The wrapper `bin/make-crud.sh:94-98` mitigates this because it always quotes `--fields "$FIELDS"`. CLAUDE.md already documents the risk, but spark itself doesn't detect this case to emit a useful message ("did you forget to quote `--fields`?").

## Improvements proposed for impeccable behavior

Listed by priority. The concrete shape of the solution will be discussed in a separate conversation — here we only set the destination.

| Priority | Improvement | Key files |
|----------|-------------|-----------|
| **P0** | Normalize acronyms in `studly()` or document the policy explicitly. Options: (a) emit error with suggestion (`APIKey → ApiKey or api_key`), (b) detect uppercase runs and convert to canonical `Studly` (`APIKey → ApiKey`), (c) preserve but fix `toSnakeCase` to treat uppercase runs as a single word (regex `(?<!^)(?=[A-Z][a-z])`). Add an architectural test. | `app/Support/Scaffolding/StringHelper.php:16-36, 59-62`; new test in `tests/Unit/Architecture/` |
| **P0** | Detect pre-write whether scaffold filenames would collide with shipping starter modules (`app/Models/{X}Model.php`, `app/Entities/{X}Entity.php`) on case-insensitive filesystems. Specific message: *"Resource name '{X}' would shadow existing starter file '{Y}' on case-insensitive filesystems."* | `ScaffoldingOrchestrator.php:94-116` |
| **P1** | Verify pre-scaffold that the target table of every `fk:` exists (query `INFORMATION_SCHEMA.TABLES`) when a DB connection is available. Otherwise generate the FK as a comment and warn in the output. | `FieldStringParser.php`, `TypeMapper.php:115-147`, `MakeCrud.php:67` |
| **P1** | Create `php spark make:crud:remove {Resource} --domain {Domain}` that inverts the graph: removes the 17 generated files, un-injects the route block, un-injects the `{x}Service()` method from the trait, un-injects the `use {Domain}DomainServices` if it becomes orphan, and optionally rolls back the migration. | new `app/Commands/MakeCrudRemove.php`; reuse `ConfigWireman`/`RouteGenerator` |
| **P1** | Make the wrapper detect that `migrate` failed (parse stdout looking for `DatabaseException` or validate tables). Independent of the upstream `spark migrate exit 0` bug, the wrapper should offer post-migrate validation and exit ≠ 0 when an error is detected. | `bin/make-crud.sh:140` (after step 3) |
| **P2** | Allow `ON DELETE`/`ON UPDATE` overrides via syntax: `fk:table:cascade`/`fk:table:restrict`/`fk:table:setnull` (with `nullable` as a hint for `setnull`). | `FieldStringParser.php`, `TypeMapper.php:115-147`, `MigrationGenerator.php` |
| **P2** | Change the orchestrator banner to distinguish `CREATED` vs `UPDATED` when an upsert appends content to an existing file. | `RouteGenerator.php:45-74`, `ConfigWireman.php` (both return path while orchestrator emits `CREATED`) |
| **P2** | `php spark make:crud` (without wrapper) with unquoted `--fields`: detect that the received field has no pipes but the rest of argv looks like field tails, suggest the wrapper. | `MakeCrud.php:42-52` |
| **P2** | Force `:required` or `:nullable` on `bool` fields to avoid the silent-default. | `FieldStringParser.php` (reject) or `DtoGenerator.php:133-187` (warn) |
| **P2** | Add `php spark make:crud --dry-run` that lists all files to be created and inserts in `Services.php`/`routes` without writing, mirroring the admin's `--dry-run`. Useful for PR review. | `MakeCrud.php`, `ScaffoldingOrchestrator.php` |
| **P2** | When `module:check` fails, suggest the next concrete command to run (e.g. regenerate the deleted file, or `make:crud:remove`). | `app/Commands/ModuleCheck.php:99-104` |

## Recommended regression tests

To seed against future regressions of these findings. Each test goes in `tests/Unit/Architecture/` or `tests/Feature/Scaffolding/` following the pattern of `MakeCrudScaffoldConventionsTest.php`:

1. **`StringHelperAcronymTest`** — input `APIKey`, `IPAddress`, `OAuth2Client`: assert that `studly` and `toSnakeCase` compose a transformation that **does not** produce single-letter segments (`a_p_i_*`).
2. **`ScaffoldingFkTargetValidationTest`** — invoke the orchestrator with `fk:nonexistent` and assert `ScaffoldConflictException` (or equivalent) **before** any files are written.
3. **`ScaffoldingCaseInsensitiveCollisionTest`** — assert that generating `APIKey/Security` when the repo already ships `ApiKey/*` raises an error with explicit suggestion.
4. **`MakeCrudUpsertMessagingTest`** — generate two resources in the same domain and assert that the second's output does not say `CREATED:` for the routes file (regex over stdout).
5. **`SoftDeleteFlagPropagationTest`** — synthesize a `ResourceSchema` with `softDelete=false` and assert that neither the migration nor the Model nor the Entity contains `deleted_at`. (Already partial; reinforce.)

## Re-verification round (2026-04-30)

After fixes implemented between the original audit and today, the same 14 scenarios were re-run
against a fresh disposable copy at `/tmp/ci4-audit/audit-kit-api/` (MySQL on the existing
`mysql` container, port 3306, databases `audit_api`/`audit_api_test`).
Raw traces in `/tmp/ci4-audit/_audit/traces/`.

### Closed findings

| Finding | Status | Evidence |
|---|---|---|
| **P0** — Acronyms produce broken table / lang names (`APIKey → a_p_i_keys`) | ✅ Closed | `StringHelper::toSnakeCase()` rewritten to treat uppercase runs as one word. C07 verified: wrapper warns about consecutive uppercase, derived names are `api_key` (table), `api-keys` (route), `$apiKey` (var). Collision with `ApiKey` starter module detected pre-write with explicit suggestion. |
| **P1** — FK to non-existent table generates 17 zombie files | ✅ Closed | `FieldStringParser` now validates FK target against `INFORMATION_SCHEMA.TABLES` before writing any file. C04 verified: `ghost_id:fk:nonexistent_table` → abort with hint `"run the migration that creates the target table first"`, zero files written. |
| **P2** — `bool` without `:required`/`:nullable` silently defaults to `false` | ✅ Closed | `FieldStringParser` now rejects `type:bool` without an explicit modifier (exit 1 with message `"bool fields must be tagged :required or :nullable"`). C02 verified. |

### Closed findings — fix phase 2026-04-30

| Finding | Status | Evidence |
|---|---|---|
| **P1** — `php spark make:crud` in non-TTY: `TypeError` + exit 0 | ✅ Fixed | `MakeCrud.php::gatherFields()` now throws `InvalidArgumentException` when `posix_isatty(STDIN)` is `false` and `--fields` is empty. Caught by existing `catch (InvalidArgumentException)` block → clean exit 1 with message. |
| **P2** — Routes file emits `CREATED:` on second resource (upsert) | ✅ Already fixed | `MakeCrud.php:125` has `$orchestrator->wasExisting($file) ? 'UPDATED' : 'CREATED'`. `ScaffoldingOrchestrator::orchestrate()` snapshots `preExisting` for every planned path including the route file before writing. C10 appeared broken in the audit temp copy (older snapshot). Current source is correct. |
| **P2** — `ON DELETE CASCADE` hardcoded for all FKs | ✅ Already fixed | `FieldStringParser.php` lines 51–54 already support `restrict` → `RESTRICT`, `setnull` → `SET NULL` as field option modifiers. `CASCADE` remains the default. |
| **P2** — No `make:crud:remove` command | ✅ Already exists | `app/Commands/MakeCrudRemove.php` is fully implemented with `ScaffoldRemover` — deletes ~17 files, un-injects route block, un-registers service factories, emits migration rollback hint. |

### New finding from re-verification · 🟠 P1 · `php spark make:crud` direct call: TypeError + exit 0 in non-TTY (C13 — severity re-evaluated)

The original audit logged this as P2 ("broken pipe with unquoted `--fields`"). On re-run the failure
mode is more serious: even with a syntactically correct `--fields=name:string:required` (no pipes),
`php spark make:crud` in a non-TTY context falls into interactive mode, `CLI::prompt()` returns
`bool` not `string`, and the command throws:

```
[TypeError]
CodeIgniter\CLI\InputOutput::input(): Return value must be of type string, bool returned
at SYSTEMPATH/CLI/InputOutput.php:46
…
```

Exit code is **0** — a CI/CD step running `php spark make:crud … && echo done` will print "done"
even though nothing was scaffolded. Re-classified to **P1** because:
1. The failure is invisible (exit 0).
2. It affects any context where `bin/make-crud.sh` is unavailable (Docker-only envs without the full repo, manual API docs, etc.).

**Mitigation exists:** `bin/make-crud.sh` is the only safe call path; the wrapper, `CLAUDE.md`,
and `ci4-api-starter/CLAUDE.md` all document this. The risk is that teams who copy the `spark`
incantation from docs rather than from the wrapper will hit this silently.

**Proposed fix:** `app/Commands/MakeCrud.php::gatherFields()` should detect non-TTY before
calling `gatherFieldsInteractively()` and exit with `CLI::EXIT_ERROR` + helpful message.

### Update — 2026-07-18: re-verified, and closed a sibling gap

Re-tested both scenarios directly against the live `dcardenasl/ci4-api-scaffolding` source
(`/Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-scaffolding`):

- `make:crud` with empty `--fields` in non-TTY: **confirmed already fixed** —
  `MakeCrud.php::gatherFields()` detects `!posix_isatty(STDIN)` and throws a clean
  `InvalidArgumentException` (exit 1) before ever reaching `CLI::prompt()`. The "Proposed fix"
  above is stale documentation from before that landed; leaving it here for history.
- `make:crud` with a **non-empty** `--fields` in non-TTY: could not reproduce the TypeError
  described above — `gatherFields()` returns immediately via `FieldStringParser::parse()` without
  touching `CLI::prompt()` when `$fieldsArg !== ''`, so this specific re-verification scenario no
  longer applies to the current source.
- **New sibling gap found and fixed:** `make:crud:remove` had the *exact same* unguarded
  `CLI::prompt('Proceed? …')` call for its delete confirmation — no TTY check at all. Reproduced
  the identical `TypeError` crash (confirmed no files are deleted before the crash — it fails
  closed, not silently). Fixed with the same `posix_isatty(STDIN)` guard pattern, requiring
  `--force` in non-interactive contexts instead of crashing. See package `CHANGELOG.md`
  ([Unreleased] section). **Not yet published** — `dcardenasl/ci4-api-scaffolding` is consumed via
  a real GitHub-tagged release, not a path-repository, so the fix lives in the package source only
  until a new version is tagged and `composer update`'d in the consumer apps.

## Appendix — how to reproduce

```bash
# 1. Spin up isolated MySQL
docker run -d --name ci4_audit_mysql --rm \
  -e MYSQL_ROOT_PASSWORD=auditpass -e MYSQL_DATABASE=audit_api \
  -p 33077:3306 mysql:8.0

# 2. Copy the starter without contaminating it
rsync -a --exclude=vendor --exclude=node_modules --exclude=.git \
  ci4-api-starter/ /tmp/ci4-audit/audit-kit-api/

# 3. Configure .env (db on 33077, audit_api/audit_api_test, JWT/encryption keys)
# 4. composer install && php spark migrate

# 5. Run the 14 scenarios — exact commands are in results.csv
cat /tmp/ci4-audit/_audit/results.csv
```

Raw traces in `/tmp/ci4-audit/_audit/traces/` (not versioned).
