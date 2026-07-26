# CRUD Desde Cero (Playbook del Template)

Guía canónica para crear un recurso CRUD nuevo en este template.

Este flujo es **recomendado por defecto**:
1. Generar scaffold primero con `bin/make-crud.sh` (o `php spark make:crud` en modo interactivo)
2. Validar el wiring con `module:check`
3. Aplicar migración, reiniciar servidor y regenerar OpenAPI
4. Personalizar solo lo que reglas de negocio exijan
5. Cerrar tests y quality gates

La creación manual de CRUD sigue siendo válida cuando se requiere estructura custom.

## 1. Pre-Checklist

1. Definir nombre del recurso (singular): `Product`
2. Definir dominio: `Catalog`
3. Definir slug de ruta (plural kebab): `products`
4. Definir modelo de acceso (lectura pública, escritura admin, etc.)
5. Definir esquema mínimo de tabla y requerimientos de auditoría
## 2. Scaffold Primero

El motor `make:crud` genera todas las capas en un solo paso (Migración, Entidad, Modelo, DTOs Request/Response, Servicio, Interface, Controller, clase OpenAPI de endpoints, archivos i18n, esqueletos de tests, wiring de servicios y archivo de rutas).

### 2.1 Recomendado: `bin/make-crud.sh` (shell-safe)

```bash
bash bin/make-crud.sh Product Catalog \
  'name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required' \
  yes
```

Firma: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Por qué preferir el wrapper:
- Las comillas simples evitan que el shell consuma los `|` dentro de `--fields`
- Ejecuta `composer cs-fix` automáticamente después de generar (mantiene contentos los pre-commit hooks)
- Imprime los comandos de seguimiento con los placeholders ya resueltos
- Seguro en entornos no-TTY (pipelines de CI, Claude Code, scripts de automatización)

### 2.2 Alternativa: `php spark make:crud` (interactivo)

Cuando quieras que el motor te pregunte por cada campo:

```bash
php spark make:crud Product --domain Catalog
```

La variante `--fields` también funciona, pero debe citarse con comillas simples:

```bash
php spark make:crud Product --domain Catalog --fields='name:string:required|searchable,price:decimal:required|filterable'
```

> En entornos no interactivos, si olvidas citar los pipes el comando pierde silenciosamente todos los modificadores y espera input interactivo que nunca llega. Esta es la razón #1 por la que `bin/make-crud.sh` es preferido.

### 2.3 El flag `SoftDelete`

- **`yes` (default)** — Añade columna `deleted_at TIMESTAMP NULL`, `useSoftDeletes = true` en el Modelo, e incluye `deleted_at` en `$dates` de la Entidad. Úsalo para entidades de negocio (Users, Orders, Products) donde quieras audit trail o restauración.
- **`no`** — Sin `deleted_at`, borrado físico. Úsalo para tablas de *lookup* (Permissions, Roles, Statuses), tablas append-only (AuditLog), o join tables.

### 2.4 Sintaxis de Campos

Formato: `nombre:tipo:modif1|modif2|modif3`. Múltiples campos separados por comas. **Siempre** envuelve la cadena completa en comillas simples cuando uses pipes.

**Tipos soportados:**

| Tipo | DB | PHP | Ejemplo |
|------|----|----|---------|
| `string` | VARCHAR(255) | string | `name:string:required` |
| `text` | TEXT | string | `description:text:nullable` |
| `int` | INT UNSIGNED | int | `stock:int:required` |
| `decimal` | DECIMAL(10,2) | float | `price:decimal:required` |
| `bool` | TINYINT | bool | `is_active:bool` |
| `email` | VARCHAR(255) | string | `email:email:required` |
| `date` | DATE | string | `birth_date:date:nullable` |
| `datetime` | DATETIME | string | `published_at:datetime` |
| `json` | JSON | array | `metadata:json:nullable` |
| `fk:tabla` | INT + FK | int | `category_id:fk:categories:required` |

**Modificadores soportados:**

| Modificador | Efecto |
|-------------|--------|
| `required` | `NOT NULL` en DB + regla `required` |
| `nullable` | `NULL` permitido + regla `permit_empty` |
| `searchable` | Incluido en `?search=` (LIKE). Añade índice B-tree implícito. |
| `filterable` | Incluido en `?filter[col]=` (match exacto). Añade índice B-tree implícito. |
| `unique` | Índice `UNIQUE` + `is_unique[table.col]` en Create DTO y Model |
| `index` | Índice B-tree no único (cuando necesitas índice pero ni searchable ni filterable aplican) |
| `fk:tabla` | FK a `tabla.id` + `is_not_unique[tabla.id]` (valida que la fila referenciada exista) |

Múltiples modificadores se combinan con `|`:

```text
email:email:required|unique
status:string:required|filterable|index
```

Nombres de campo inválidos o reservados se rechazan al inicio (keywords PHP, palabras reservadas MySQL, duplicados, y colisiones con `id`/`created_at`/`updated_at`/`deleted_at`).

Qué genera el scaffold:
1. Archivos de migración de base de datos
2. Controller, Service, Interface
3. DTOs Request/Response
4. Model/Entity
5. Placeholder OpenAPI de endpoints (`app/Documentation/...`)
6. Archivos i18n (`en` y `es`)
7. Esqueletos de tests Unit/Integration/Feature
8. Registro de servicio en `app/Config/Services.php` (si faltaba)

Qué **no** genera:
1. Migraciones de base de datos
2. Repositorio específico por dominio (`*Repository`)
3. Reglas finales de negocio y validaciones específicas

## 3. Post-Scaffolding (Qué hace cada comando de seguimiento)

Tras `make:crud`, ejecuta estos comandos en orden. `bin/make-crud.sh` los imprime al final; esta sección explica qué hace cada uno para que sepas dónde mirar si algo falla.

### 3.1 `php spark module:check Product --domain Catalog`

Chequeo estático que verifica que todo artefacto esperado se generó y quedó cableado:

- Existen los ~13 archivos (Controller, Service, Interface, 4 DTOs, clase OpenAPI, Model, Entity, 2 archivos de idioma, 3 de tests).
- Los namespaces coinciden con el dominio.
- El servicio y su response mapper quedaron registrados en `app/Config/{Domain}DomainServices.php`.
- El archivo de rutas `app/Config/Routes/v1/{domain-kebab}.php` referencia al nuevo controller.
- No quedan placeholders `markTestIncomplete` / `TODO` / `FIXME` en código generado.

**No** valida: SQL de la migración, existencia de tablas FK, lógica de negocio.

### 3.2 `php spark migrate`

Aplica la migración generada en el paso 2. Revísala primero:

```bash
cat app/Database/Migrations/*_Create{Plural}Table.php
```

Verifica el nombre de tabla (snake_case plural), la presencia de `deleted_at`, índices y FK.

### 3.3 Reiniciar el servidor de desarrollo

```bash
pkill -f 'spark serve'; php spark serve --port 8080 &
```

**Obligatorio.** CodeIgniter 4 carga archivos de rutas al arrancar desde `app/Config/Routes/v1/*.php`. Archivos nuevos generados desde el último arranque son invisibles hasta reiniciar.

### 3.4 `php spark swagger:generate`

Relee los DTOs (schemas) y `app/Documentation/{Domain}/*Endpoints.php` (paths) y escribe el spec unificado en `public/swagger.json`. Los clientes de API y Swagger UI leen de ahí — sin este paso, los endpoints nuevos no aparecen en la documentación aunque respondan correctamente.

### 3.5 `composer cs-fix` (solo si editaste archivos generados)

El motor emite código PSR-12, pero si tocas manualmente un archivo generado puedes introducir violaciones que el pre-commit hook rechaza. `bin/make-crud.sh` corre `cs-fix` automáticamente tras generar; córrelo manual tras ediciones.

## 5. Alinear Capa de Persistencia

1. Ajustar `Entity` (`casts`/`dates`) según migración
2. Ajustar `Model`:
   - `allowedFields`
   - `validationRules`
   - `searchableFields`, `filterableFields`, `sortableFields`
   - traits (`Filterable`, `Searchable`, `Auditable`) cuando aplique

## 6. Cerrar Contratos DTO

1. Request DTOs extienden `BaseRequestDTO` y se mantienen `readonly`
2. Implementar `rules()`, `map()`, `toArray()` completos
3. Response DTO con atributos OpenAPI `#[OA\Property]` y `fromArray()`
4. Mantener DTOs alineados al contrato API (sin exponer campos internos)

## 7. Servicio + Estrategia de Repositorio

Reglas del servicio:
1. Servicio puro (sin construir respuestas HTTP)
2. Lecturas retornan DTOs; comandos retornan `OperationResult`
3. Usar transacciones en escrituras

Estrategia por defecto:
1. Usar `GenericRepository` vía `RepositoryInterface` para CRUD estándar
2. Crear `*RepositoryInterface` + implementación dedicada solo cuando:
   - existen consultas de dominio no triviales
   - hay reglas de persistencia reutilizables entre servicios
   - se requieren métodos de consulta custom para claridad/testability

## 8. Registrar Dependencias

1. Confirmar registro de servicio en `app/Config/Services.php`
2. Si hay repositorio dedicado, registrar también sus factories
3. Mantener DI tipada por interfaces cuando corresponda

## 9. Controller, Routes, OpenAPI e i18n

1. Controller extiende `ApiController`
2. Resolver servicio en `resolveDefaultService()`
3. Usar patrón `handleRequest('method', RequestDTO::class)`
4. Agregar/verificar rutas en `app/Config/Routes.php` con filtros correctos
5. Completar docs de endpoints en `app/Documentation/{Domain}/...`
6. Mantener paridad de idioma en `app/Language/en` y `app/Language/es`

## 10. Testing y Quality Gates

1. Completar Unit tests (comportamiento/contratos de servicio)
2. Completar Integration tests (modelo/persistencia)
3. Completar Feature tests (status HTTP + JSON + autorización)
4. Ejecutar:

```bash
php spark tests:prepare-db
composer quality
php spark swagger:generate
```

## FAQ

### ¿Cuándo ejecutar migraciones?
Después de ejecutar `make:crud` y validar el esqueleto con `module:check`.

### ¿`make:crud` crea migraciones?
Sí. Utiliza un esquema único para sincronizar base de datos, DTOs y servicios.

### ¿Cuándo necesito repositorio dedicado?
Cuando el CRUD genérico no alcanza y las consultas/reglas de persistencia del dominio se vuelven explícitas/complejas.

### ¿`make:crud` es obligatorio?
Es el estándar recomendado por defecto. La creación manual está permitida — pero ten en cuenta que la estructura generada es lo que esperan los tests de arquitectura y el admin starter.

### ¿Cuándo usar `SoftDelete=no`?
El default `yes` encaja para entidades de negocio (Users, Orders, Products). Usa `no` para:
- Tablas de *lookup* con filas finitas y poco cambiantes (Permissions, Roles, Statuses)
- Tablas append-only donde nunca se borran filas (AuditLog, IdempotencyKey)
- Join tables de relaciones many-to-many (Role×Permission)

## Troubleshooting

### `php spark make:crud` me pregunta por los campos aunque pasé `--fields='…'`

Estás en un entorno no-TTY y tu shell consumió los pipes. Usa `bin/make-crud.sh` — el wrapper cita correctamente. Si debes usar `php spark` directo, envuelve `--fields` en comillas **simples**: `--fields='name:string:required|searchable'`.

### Las rutas siguen devolviendo 404 tras el scaffold

No reiniciaste el servidor. CI4 solo carga rutas al arrancar:

```bash
pkill -f 'spark serve'; php spark serve --port 8080 &
```

### La migración falla con "tabla X no existe" al usar `fk:X`

La tabla target aún no fue migrada. O el módulo target no está generado, o su migración se ordena después que la tuya. Los archivos de migración son `YYYY-MM-DD-HHMMSS_…`; los anteriores corren primero. Si generas dos recursos en el mismo segundo, el orden depende del scan del filesystem — genera y migra primero el target, después el que tiene la FK.

### Swagger UI no muestra el nuevo endpoint

Ejecuta `php spark swagger:generate`. El spec no se genera en request-time, es un artefacto estático en `public/swagger.json` que debe regenerarse tras cambios en DTOs o `app/Documentation/`.

### El pre-commit hook rechaza archivos generados (PHP CS Fixer)

Si usaste `bin/make-crud.sh` no debería ocurrir (corre `cs-fix` automáticamente). Si usaste `php spark make:crud` directo:

```bash
composer cs-fix && git add -u && git commit
```

**No uses `--no-verify`.** El hook existe justo para cazar estos casos.

### `ScaffoldConflictException: files already exist`

Algunos de los ~13 archivos de un scaffold previo aún están en disco. O terminas el módulo anterior (migrate, commit) o borras los archivos obsoletos manualmente. El orquestrator ahora hace rollback de escrituras parciales, así que esto no debería ocurrir desde un run abortado en condiciones normales.

### Campo `class` / `order` / `function` rechazado con error

Funcionamiento correcto — `FieldNameValidator` rechaza keywords PHP, palabras reservadas MySQL, duplicados, y `id`/`created_at`/`updated_at`/`deleted_at` (gestionadas por el motor). Renómbralo a algo específico del dominio (`order_number`, `class_name`, etc.).
