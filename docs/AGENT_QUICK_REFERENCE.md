# ⚡ Agent & Developer Quick Reference Guide

This "Cheat Sheet" is designed for rapid onboarding and high-speed development.

## 🚀 Core Commands

| Command | Purpose | When to use? |
|---------|---------|--------------|
| `bash bin/make-crud.sh <Res> <Dom> '<fields>'` | **Scaffold Module (recommended)** | Starting a new CRUD resource — shell-safe, non-TTY friendly. |
| `php spark make:crud {Name}` | **Scaffold Module (interactive)** | When you want to be prompted for each field. |
| `php spark module:check {Name} --domain {Dom}` | **Validate wiring** | Immediately after scaffolding. |
| `php spark migrate` | **Apply DB changes** | After scaffolding, review migration then apply. |
| `pkill -f 'spark serve'; php spark serve &` | **Restart server** | Required after scaffolding — new route files aren't hot-loaded. |
| `php spark swagger:generate` | **Update OpenAPI** | After adding endpoints or DTOs. |
| `composer quality` | **Full Health Check** | Before pushing any code. |
| `composer cs-fix` | **Fix Linting** | To auto-format your code. |

## 🏗️ Scaffolding Syntax

Signature: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

**Available Types:** `string`, `text`, `int`, `bool`, `decimal`, `email`, `date`, `datetime`, `fk`, `json`.
**Common Options:** `required`, `nullable`, `searchable`, `filterable`, `fk:tableName`.

*Example:*
`bash bin/make-crud.sh Product Catalog 'name:string:required|searchable,category_id:fk:categories:required' yes`

## ✅ Quality Standards Checklist

1.  **Immutability:** Always use `readonly class` for DTOs.
2.  **DTO-First:** No direct input mapping in Controllers; use `RequestDataCollector`.
3.  **Audit:** Use the `Auditable` trait for any model with sensitive data.
4.  **Tests:** New services must include Unit tests; controllers must have Feature tests.
5.  **Docs:** Ensure OpenAPI tags and summaries are clear and grouped by Domain.

## 📁 File Structure Map (Layered API)

- `app/Controllers/Api/V1/{Domain}/` -> Entry point.
- `app/DTO/Request/{Domain}/` -> Request validation.
- `app/DTO/Response/{Domain}/` -> Response transformation.
- `app/Interfaces/{Domain}/` -> Service contracts.
- `app/Services/{Domain}/` -> Business logic.
- `app/Models/` -> Database orchestration.
- `app/Documentation/{Domain}/` -> OpenAPI definitions.
