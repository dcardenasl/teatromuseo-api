# Referencia rápida para agentes — `teatromuseo-api`

Este repositorio es el Hub de Teatro Museo. Lee `CLAUDE.md` y `TASKS.md` antes
de editar. Gestiona usuarios, RBAC/IAM, emisión de JWT y dominios alojados en
el Hub.

## Comandos

```bash
php spark serve --port 8180

# CRUD: usa siempre el wrapper en entornos no interactivos.
bash vendor/bin/make-crud.sh ResourceName DomainName 'field:type:rules,...' yes [route]
php spark module:check ResourceName --domain DomainName
php spark migrate
php spark swagger:generate

composer test:unit
composer test:integration
composer test:feature
composer quality
composer cs-fix
```

Reinicia el servidor después de generar recursos: las rutas nuevas no se
cargan en caliente.

## Reglas de arquitectura

```text
Controller → RequestDTO → Service → Model/Entity → ResponseDTO
```

- Los Request DTO extienden `BaseRequestDTO`, son `readonly` y validan en el
  constructor.
- Los servicios no conocen HTTP; devuelven DTOs en lecturas y
  `OperationResult` o excepciones en comandos.
- Los controladores extienden `ApiController`, resuelven explícitamente el
  servicio por defecto y usan `handleRequest()`.
- Los esquemas OpenAPI viven en DTOs y la documentación de endpoints en
  `app/Documentation/`.
- Los permisos usan `.` (`users.write`, `iam.admin-access`), nunca `:`.
- El Hub es el único que emite JWT y posee las tablas IAM.

## Estructura

- `app/DTO/Request/` y `app/DTO/Response/` — contratos de la API.
- `app/Controllers/Api/V1/` — frontera HTTP.
- `app/Services/` — lógica de negocio.
- `app/Models/`, `app/Entities/`, `app/Repositories/` — persistencia.
- `app/Config/Routes/v1/` — rutas versionadas.
- `tests/Unit`, `tests/Integration`, `tests/Feature` — suites de pruebas.

Nunca hagas commit de `.env`, tokens o credenciales. Prefiere los scripts de
Composer para tests, porque desactivan coverage cuando no hay Xdebug.
