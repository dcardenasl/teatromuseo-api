# Auditoría de `bin/make-crud.sh` — 2026-04-29

> Plan ejecutado: 14 escenarios (C01–C14) sobre una copia desechable del starter en `/tmp/ci4-audit/audit-kit-api/` con MySQL 8 aislado en puerto 33077. Evidencia bruta en `/tmp/ci4-audit/_audit/traces/` (no versionada). Esta auditoría es **solo diagnóstico** — los parches se discutirán por separado.

## Resumen ejecutivo

| ID  | Escenario                          | Resultado            | Severidad |
|-----|------------------------------------|----------------------|-----------|
| C01 | happy path mínimo                  | ✅ PASS              | —         |
| C02 | multi-campo (string/decimal/text/bool/unique) | ✅ PASS   | —         |
| C03 | FK válida + filterable             | ✅ PASS              | P2        |
| C04 | FK rota (`fk:nonexistent_table`)   | ⚠ generó 17 archivos zombi | **P1** |
| C05 | `soft-delete=no`                   | ✅ PASS              | —         |
| C06 | route slug custom                  | ✅ PASS              | —         |
| C07 | acrónimo `APIKey` en `Security`    | ❌ FAIL (colisiona con módulo del starter) | **P0** |
| C08 | campo reservado `order`            | ✅ rechazo limpio    | —         |
| C09 | idempotencia (segunda pasada)      | ✅ rechazo limpio    | —         |
| C10 | segundo recurso, mismo dominio     | ✅ PASS              | P2        |
| C11 | argumentos faltantes               | ✅ rechazo limpio    | —         |
| C12 | soft-delete `maybe`                | ✅ rechazo limpio    | —         |
| C13 | `php spark make:crud` con pipe sin quotes | ❌ broken pipe (confirma necesidad del wrapper) | P2 |
| C14 | `module:check` con archivo borrado | ✅ detección correcta| —         |

**Conteo por severidad:** P0 = 1 · P1 = 1 · P2 = 4 · sin severidad = 9.

## Lo que funciona bien

1. **Generación cruda y atómica.** El happy path produce ~17 archivos (DTO×4, Servicio×2, Controlador + OpenAPI, Modelo + Entity, Migración, Lang×2, Tests×3) en una sola pasada, todos sintácticamente válidos. Verificación: C01, C02. Implementación: `app/Commands/MakeCrud.php:40-115` orquesta `ScaffoldingOrchestrator` (`app/Support/Scaffolding/ScaffoldingOrchestrator.php`).
2. **Idempotencia y rollback ante conflicto.** Una segunda pasada del mismo recurso aborta antes de escribir nada, con un mensaje que enumera los archivos en conflicto y exit code 1. C09 confirma cero mutaciones del working tree después del rechazo. Hook: `ScaffoldingOrchestrator.php:94-116` (`validateFilesDoNotExist`) + rollback en `:63-92`.
3. **Validación previa de nombres de campo.** `FieldNameValidator` (en `app/Support/Scaffolding/FieldNameValidator.php`) rechaza palabras reservadas de PHP/MySQL y colisiones con columnas gestionadas por el motor (`id`, `created_at`, …) con mensajes claros. C08 (`order` MySQL keyword) → exit 1, mensaje *“Pick a more specific name (e.g. order_number)”*, cero archivos creados.
4. **Cableado idempotente para recursos múltiples.** Al añadir un segundo recurso al mismo dominio, `ConfigWireman` y `RouteGenerator` reutilizan el trait y el archivo de rutas, sin duplicar `use`/`require`. C10 verificó dos servicios `productService()` + `categoryService()` y dos bloques de rutas en `app/Config/Routes/v1/catalog.php`.
5. **Soft-delete respetado fielmente.** Cuando se pasa `no`, la migración omite `deleted_at`, el modelo pone `useSoftDeletes = false`, y la Entity excluye la columna de `$dates`. C05 confirmado (verificación con `grep -c 'deleted_at' migration | grep useSoftDeletes`).
6. **`module:check` confiable.** `app/Commands/ModuleCheck.php:24-109` valida 13 archivos, placeholders, cableado de servicios y rutas. C14 demostró que detecta exactamente el archivo borrado y retorna exit 1 con la ruta absoluta.
7. **Wrapper protege del *non-TTY hang*.** `bin/make-crud.sh:91-107` captura el output completo de spark a un tempfile, filtra solo `CREATED|WIRING|✅` en éxito, y vuelca todo en fallo. C13 confirma que la llamada directa sin quotes (pipe shell-expandido) muere, mientras que la wrapper resuelve el quoting correctamente.
8. **Auto-format.** El paso 2 corre `composer cs-fix` (`bin/make-crud.sh:111`) — los archivos generados pasan el pre-commit hook a la primera. Verificado en todos los escenarios PASS: ninguno necesitó re-formato manual.
9. **Test architectural enforcement.** `tests/Unit/Architecture/MakeCrudScaffoldConventionsTest.php` ya valida convenciones del scaffold (BaseAuditableModel, filtros estándar en rutas, `public function rules()` en DTOs). Buen patrón a expandir con regresiones para los hallazgos de esta auditoría.

## Lo que funciona mal

### 🔴 P0 · Acrónimos en el nombre del recurso colisionan o producen tablas inservibles

**Reproducción (C07):**
```bash
bash bin/make-crud.sh APIKey Security 'name:string:required' yes
# → exit 1 en macOS (case-insensitive FS):
#   Scaffolding aborted to prevent overwriting existing work...
#   - app/Entities/APIKeyEntity.php
#   - app/Models/APIKeyModel.php
#   - app/Language/en/APIKeys.php
```

**Causa raíz:** `StringHelper::studly()` (`app/Support/Scaffolding/StringHelper.php:16-36`) preserva el casing interno de identificadores ya alfanuméricos (línea 27-29 — comentario explícito sobre evitar `Schoolcategory`). Para `APIKey` esto produce ficheros `APIKeyEntity.php`, `APIKeyModel.php`, etc. El starter ya envía `app/Models/ApiKeyModel.php` y compañía. En sistemas case-insensitive (HFS+/APFS, NTFS), ambos nombres resuelven al mismo path y el detector de conflictos los marca como pre-existentes. **En Linux ext4/btrfs (case-sensitive), el scaffold NO detectaría la colisión y sobrescribiría parcialmente artefactos del starter** — escenario considerablemente peor.

**Bug subyacente acoplado:** Aunque el fichero no colisionara, `StringHelper::toSnakeCase()` (`StringHelper.php:59-62`) usa el regex `(?<!^)[A-Z]` y produciría tabla `a_p_i_keys` para `APIKey`. La función `getResourcePluralSnakeCase()` (`ResourceSchema.php:47-49`) consume directamente esa lógica. Es la misma clase de bug que documenta M07 en la auditoría del admin (a_p_i_keys/ vista, lang `'a p i key'`).

**Impacto:** cualquier recurso con dos o más mayúsculas consecutivas (HTTPRequest, IPAddress, JSONPayload, OAuthToken, ID‑related…) genera artefactos inutilizables o pisa código del starter sin aviso.

---

### 🟠 P1 · FK con tabla destino inexistente: scaffold ✅ + migrate “OK” pero falla silenciosamente

**Reproducción (C04):**
```bash
bash bin/make-crud.sh Bad Sales 'ghost_id:fk:nonexistent_table:required' yes
# → exit 0, 17 archivos creados
php spark migrate
# → imprime DatabaseException ‘Failed to open the referenced table nonexistent_table’
# → SPARK PROCESS EXIT CODE = 0  (¡no fallo en CI!)
```

**Causa raíz #1 — scaffold no valida FK:** `FieldStringParser` (`app/Support/Scaffolding/FieldStringParser.php`) acepta cualquier identificador después de `fk:`. `TypeMapper` (`app/Support/Scaffolding/TypeMapper.php:115-147`) genera `is_not_unique[<table>.id]` y la migración añade `addForeignKey('ghost_id', 'nonexistent_table', 'id', 'CASCADE', 'CASCADE')` sin verificar la existencia previa del referente. Resultado: 17 archivos zombi quedan en el repo y la única señal es la excepción de migración (que muchos devs leen como warning).

**Causa raíz #2 — `spark migrate` no devuelve exit ≠ 0 al fallar:** observación complementaria de framework (CI4 4.7.0). Ejemplo:
```
$ php spark migrate
[CodeIgniter\Database\Exceptions\DatabaseException]
Failed to open the referenced table 'nonexistent_table'
$ echo $?
0
```
Re-corrida idéntica. Un pipeline de CI que ejecute `php spark migrate && next-step` no se entera. Esto **no es** un bug del scaffold, pero amplifica el daño cuando combinado con el causa raíz #1.

**Causa raíz #3 — no existe `make:crud:remove`:** el dev queda con 17 archivos huérfanos + entradas en `Services.php` + bloque inyectado en routes. Limpieza manual completa lleva ~10 pasos (ver §“Limpieza manual” en `docs/template/CRUD_FROM_ZERO.md`).

---

### 🟡 P2 · Hardcoding de `ON DELETE CASCADE` para FKs

`MigrationGenerator` (vía `TypeMapper`) emite siempre `addForeignKey(col, table, 'id', 'CASCADE', 'CASCADE')` (verificado en C03: `app/Database/Migrations/*CreateOrderItemsTable.php`). No hay forma de pedir `RESTRICT` ni `SET NULL` desde la sintaxis `fk:table[:nullable]`. Para muchos dominios (orders→customers, audit_logs→users) cascade-delete es destructivo. Workaround actual: editar la migración generada antes de aplicarla.

---

### 🟡 P2 · Output del scaffolding marca rutas “CREATED” cuando son UPDATED

Verificación (C10): al añadir un segundo recurso al mismo dominio, el stdout dice:
```
CREATED: /private/tmp/ci4-audit/audit-kit-api/app/Config/Routes/v1/catalog.php
```
…aunque el archivo ya existía. El `RouteGenerator` realiza una *upsert* (inyecta el bloque), no un *create*. El mensaje es engañoso para devs que escanean los logs buscando “qué archivos nuevos tengo que git add”.

---

### 🟡 P2 · `bool` sin `:required` ni `:nullable` queda implícitamente *permit_empty + default false*

C02 generó:
```php
public bool $is_paid;                               // tipo no nullable
'is_paid' => 'permit_empty|boolean_like',          // permite no-enviado
$this->is_paid = (bool) ($data['is_paid'] ?? false); // default silencioso
```
Comportamiento ambiguo: el cliente puede omitir `is_paid` y obtiene `false` sin saberlo, mientras la firma OpenAPI sugiere que el campo es de tipo `boolean` simple. Mejor opción: rechazar fields `:bool` sin modificador explícito o documentar la regla por defecto.

---

### 🟡 P2 · Llamada directa a `php spark make:crud` con pipes sin quotes muere por broken pipe

Confirmado en C13: la shell consume el `|searchable` como inicio de pipeline, `searchable` no existe (exit 127), y spark se cierra con `fwrite(): Broken pipe`. El wrapper `bin/make-crud.sh:94-98` mitiga esto porque siempre cita `--fields "$FIELDS"`. CLAUDE.md ya documenta el riesgo, pero spark mismo no detecta este caso para emitir un mensaje útil (“did you forget to quote `--fields`?”).

## Mejoras propuestas para impecabilidad

Listadas por prioridad. La forma concreta de la solución se discutirá en una conversación separada — aquí dejamos solo el destino.

| Prioridad | Mejora | Archivos clave |
|----------|--------|----------------|
| **P0** | Normalizar acrónimos en `studly()` o explicitar política. Opciones: (a) emitir error con sugerencia (`APIKey → ApiKey ó api_key`), (b) detectar runs de mayúsculas y convertir a `Studly` canónico (`APIKey → ApiKey`), (c) preservar pero corregir `toSnakeCase` para tratar runs de mayúsculas como una sola palabra (regex `(?<!^)(?=[A-Z][a-z])`). Añadir test architectural. | `app/Support/Scaffolding/StringHelper.php:16-36, 59-62`; nuevo test en `tests/Unit/Architecture/` |
| **P0** | Detectar pre-write si los nombres de archivo del scaffold colisionan con módulos shipping (`app/Models/{X}Model.php`, `app/Entities/{X}Entity.php`) por case-insensitive. Mensaje específico: *“Resource name '{X}' would shadow existing starter file '{Y}' on case-insensitive filesystems.”* | `ScaffoldingOrchestrator.php:94-116` |
| **P1** | Verificar previa al scaffold que la tabla destino de cada `fk:` exista (consulta a `INFORMATION_SCHEMA.TABLES`) cuando hay conexión a DB. En su defecto, generar el FK comentado y avisar en el output. | `FieldStringParser.php`, `TypeMapper.php:115-147`, `MakeCrud.php:67` |
| **P1** | Crear `php spark make:crud:remove {Resource} --domain {Domain}` que invierta el grafo: borra los 17 archivos generados, des-inyecta el bloque de rutas, des-inyecta el método `{x}Service()` del trait, des-inyecta el `use {Domain}DomainServices` si queda huérfano, y opcionalmente revierte la migración. | nuevo `app/Commands/MakeCrudRemove.php`; reuso de `ConfigWireman`/`RouteGenerator` |
| **P1** | Hacer que el wrapper detecte `migrate` falló (parsear stdout buscando `DatabaseException` o validar tablas). Independiente del bug upstream de `spark migrate exit 0`, el wrapper debe ofrecer una validación post-migrate y exit ≠ 0 si detecta error. | `bin/make-crud.sh:140` (después del paso 3) |
| **P2** | Permitir override de `ON DELETE`/`ON UPDATE` desde la sintaxis: `fk:table:cascade`/`fk:table:restrict`/`fk:table:setnull` (con `nullable` como hint para `setnull`). | `FieldStringParser.php`, `TypeMapper.php:115-147`, `MigrationGenerator.php` |
| **P2** | Cambiar el banner del orchestrator para distinguir `CREATED` vs `UPDATED` cuando un upsert añade contenido a un archivo existente. | `RouteGenerator.php:45-74`, `ConfigWireman.php` (ambos retornan path mientras orchestrator emite `CREATED`) |
| **P2** | `php spark make:crud` (sin wrapper) con `--fields` no citado: detectar que el campo recibido no contiene pipes pero el resto de argv parece colas de fields, sugerir el wrapper. | `MakeCrud.php:42-52` |
| **P2** | Forzar `:required` o `:nullable` en campos `bool` para evitar el silent-default. | `FieldStringParser.php` (rechazar) o `DtoGenerator.php:133-187` (warn) |
| **P2** | Añadir `php spark make:crud --dry-run` que liste todos los archivos que se crearían y los inserts en `Services.php`/`routes` sin escribir, simétrico al `--dry-run` del admin. Útil para revisión en PR. | `MakeCrud.php`, `ScaffoldingOrchestrator.php` |
| **P2** | Cuando `module:check` falla, sugerir el siguiente comando concreto a correr (por ejemplo, regenerar el archivo borrado, o `make:crud:remove`). | `app/Commands/ModuleCheck.php:99-104` |

## Lista de regresiones recomendadas

Para sembrar contra futuras regresiones de los hallazgos. Cada test va en `tests/Unit/Architecture/` o `tests/Feature/Scaffolding/` siguiendo el patrón de `MakeCrudScaffoldConventionsTest.php`:

1. **`StringHelperAcronymTest`** — input `APIKey`, `IPAddress`, `OAuth2Client`: assertear que `studly` y `toSnakeCase` componen una transformación que **no** produce singletons (`a_p_i_*`).
2. **`ScaffoldingFkTargetValidationTest`** — invocar el orquestador con `fk:nonexistent` y assertear `ScaffoldConflictException` (o equivalente) **antes** de escribir cualquier archivo.
3. **`ScaffoldingCaseInsensitiveCollisionTest`** — assertear que generar `APIKey/Security` cuando el repo ya trae `ApiKey/*` lanza un error con sugerencia explícita.
4. **`MakeCrudUpsertMessagingTest`** — generar dos recursos en el mismo dominio y assertear que la salida del segundo no dice `CREATED:` para el archivo de rutas (regex sobre stdout).
5. **`SoftDeleteFlagPropagationTest`** — sintetizar un `ResourceSchema` con `softDelete=false` y assertear que ni la migración ni el Model ni la Entity contienen `deleted_at`. (Ya existe parcialmente; reforzar.)

## Apéndice — cómo reproducir

```bash
# 1. Spin up MySQL aislado
docker run -d --name ci4_audit_mysql --rm \
  -e MYSQL_ROOT_PASSWORD=auditpass -e MYSQL_DATABASE=audit_api \
  -p 33077:3306 mysql:8.0

# 2. Copiar el starter sin contaminarlo
rsync -a --exclude=vendor --exclude=node_modules --exclude=.git \
  ci4-api-starter/ /tmp/ci4-audit/audit-kit-api/

# 3. Configurar .env (db en 33077, audit_api/audit_api_test, JWT/encryption keys)
# 4. composer install && php spark migrate

# 5. Ejecutar los 14 escenarios — los comandos exactos están en results.csv
cat /tmp/ci4-audit/_audit/results.csv
```

Trazas crudas en `/tmp/ci4-audit/_audit/traces/` (no versionadas).
