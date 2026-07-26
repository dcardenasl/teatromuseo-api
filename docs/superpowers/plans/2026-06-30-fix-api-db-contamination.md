# API Database Contamination Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the API project with the `ci4_website_builder` database contract, remove stale `ci4_api` defaults, and rebuild the live database so `cms_` tables no longer appear in the API schema.

**Architecture:** The API already owns the hub/RBAC schema; the issue is configuration drift between `.env`, Docker defaults, and PHP config. We will make the database name consistent across runtime files, then reset and recreate the live MySQL schema using the API migrations and seeders, and finally verify that the resulting schema contains only API-owned tables.

**Tech Stack:** CodeIgniter 4, Bash, Docker Compose, MySQL, PHPUnit

---

### Task 1: Normalize API database naming

**Files:**
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/app/Config/Database.php`
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/docker-compose.yml`
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/docker/entrypoint.sh`
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/.env.docker.example`
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/GETTING_STARTED.md`

- [ ] **Step 1: Update the runtime defaults**

```php
// app/Config/Database.php
'database' => 'ci4_website_builder',
```

- [ ] **Step 2: Update Docker defaults**

```yaml
# docker-compose.yml
MYSQL_DATABASE: ${MYSQL_DATABASE:-ci4_website_builder}
```

- [ ] **Step 3: Update container bootstrap**

```bash
# docker/entrypoint.sh
upsert_env_key 'database.default.database' "${MYSQL_DATABASE:-ci4_website_builder}"
```

- [ ] **Step 4: Update the Docker env example and starter guide**

```dotenv
# .env.docker.example
database.default.database = ci4_website_builder
MYSQL_DATABASE = ci4_website_builder
```

```md
# GETTING_STARTED.md
# database.default.database = ci4_website_builder
```

- [ ] **Step 5: Verify the files no longer reference `ci4_api`**

Run: `rg -n "ci4_api" app/Config/Database.php docker-compose.yml docker/entrypoint.sh .env.docker.example GETTING_STARTED.md`
Expected: no matches in the modified runtime files

### Task 2: Repair the env-check tests

**Files:**
- Modify: `/Users/davidcardenas/Developer/PHP/ci4-website-starter/ci4-website-builder-api/tests/Unit/Commands/EnvCheckTest.php`

- [ ] **Step 1: Replace the stale database-name fixture values**

```php
'database.default.database' => 'ci4_website_builder',
```

- [ ] **Step 2: Run the unit test**

Run: `vendor/bin/phpunit tests/Unit/Commands/EnvCheckTest.php --testdox`
Expected: pass

### Task 3: Reset and rebuild the live API database

**Files:**
- No source files. Operates on MySQL databases `ci4_website_builder` and `ci4_website_builder_test`.

- [ ] **Step 1: Drop and recreate both schemas**

Run:
```bash
docker exec mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS ci4_website_builder; CREATE DATABASE ci4_website_builder CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; DROP DATABASE IF EXISTS ci4_website_builder_test; CREATE DATABASE ci4_website_builder_test CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

- [ ] **Step 2: Rebuild the schema**

Run: `php spark migrate`
Expected: all API migrations apply cleanly

- [ ] **Step 3: Seed the RBAC bootstrap**

Run: `php spark db:seed RbacBootstrapSeeder`
Expected: seeded hub apps, permissions, and roles are present

- [ ] **Step 4: Prepare the test database**

Run: `php spark tests:prepare-db`
Expected: test DB is ready for PHPUnit

- [ ] **Step 5: Confirm no `cms_` tables remain in the API schema**

Run:
```bash
docker exec mysql mysql -uroot -proot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ci4_website_builder' AND table_name LIKE 'cms\\_%';"
```
Expected: `0`

### Task 4: Verify behavior end to end

**Files:**
- No source files.

- [ ] **Step 1: Run the API test suite**

Run: `vendor/bin/phpunit`
Expected: pass

- [ ] **Step 2: Spot-check schema tables**

Run:
```bash
docker exec mysql mysql -uroot -proot -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='ci4_website_builder' ORDER BY table_name;"
```
Expected: only API-owned tables such as `applications`, `permissions`, `roles`, `user_roles`, `files`, `audit_logs`, etc.
