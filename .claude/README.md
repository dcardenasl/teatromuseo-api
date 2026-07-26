# Claude Code Agent for CI4 API Starter

This directory contains a specialized Claude Code agent designed specifically for this project.

## What is this?

[Claude Code](https://claude.ai/code) is Anthropic's official CLI for working with code. This project includes a custom agent that acts as an expert architect who deeply understands this API starter template's patterns and conventions.

## The Agent: `ci4-api-crud-builder`

This agent is a senior PHP/CodeIgniter 4 expert that knows:
- The complete layered architecture (Controller → Service → Model → Entity)
- Declarative controller pattern (`resolveDefaultService()` + `handleRequest(...)`)
- All custom exceptions and when to use them
- The ApiResponse library and standardized formats
- Testing patterns across Unit, Integration, and Feature tests
- JWT authentication flow and filter configuration
- OpenAPI/Swagger annotation conventions

### When to use it

The agent automatically activates when you ask Claude Code to:
- Create new CRUD resources or endpoints
- Add new entities, models, services, or controllers
- Modify or extend existing CRUD operations
- Add fields to existing resources
- Create tests following project patterns

### Example usage

```bash
# In Claude Code CLI
> Necesito crear un CRUD completo para gestionar productos

# Claude Code will automatically use the ci4-api-crud-builder agent
# to implement the complete resource following all project patterns
```

The agent will:
1. **Research** existing patterns in your codebase
2. **Scaffold first** with `bash bin/make-crud.sh ...` for new CRUD resources (the wrapper is mandatory in non-TTY environments like Claude Code; `php spark make:crud` is available for interactive sessions)
3. **Validate bootstrap** with `php spark module:check ...`
4. **Plan** the implementation (migration, entity/model alignment, DTO/service/controller/tests)
5. **Implement** following exact architectural patterns
6. **Test** with Unit, Integration, and Feature tests
7. **Document** with OpenAPI annotations in `app/Documentation/`
8. **Apply repository strategy** (`GenericRepository` by default, dedicated repositories only for non-trivial queries)

## What's included

- `agents/ci4-api-crud-builder.md` - The agent definition with all rules and patterns
- `settings.local.json` - Your personal Claude Code settings (gitignored)

Security note:
- Never hardcode JWTs, API keys, or secrets in `settings.local.json`; use environment variables at runtime.

## How it works

When you use Claude Code in this project:

1. **Generic Claude Code** reads `CLAUDE.md` in the project root for general project guidance
2. **Specialized Agent** (this one) activates automatically when you're working on CRUD operations

The agent ensures that every new resource follows the exact same patterns as existing code, maintaining architectural consistency across your API.

## Benefits

- ✅ **Zero learning curve** - The agent knows all the patterns
- ✅ **Architectural consistency** - Never deviates from established conventions
- ✅ **Complete implementation** - Generates scaffolded CRUD layers and guides explicit migration creation
- ✅ **Best practices** - Follows security, testing, and documentation standards
- ✅ **Bilingual** - Responds in Spanish or English based on your input

## Requirements

- [Claude Code CLI](https://claude.ai/code) installed
- Access to Claude (Anthropic account)

## Learn more

- Main documentation: `CLAUDE.md` in project root
- CodeIgniter 4 docs: https://codeigniter.com/user_guide/
- Claude Code docs: https://docs.anthropic.com/en/docs/claude-code
