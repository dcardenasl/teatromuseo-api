# TASKS — teatromuseo-api (Hub)

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

> Saneamiento arquitectónico — auditoría del 2026-08-05.
> **Contexto, evidencia y rutas exactas:** [`../docs/plan/2026-08-05-saneamiento-arquitectonico.md`](../docs/plan/2026-08-05-saneamiento-arquitectonico.md)
> Orden y dependencias cross-repo: [`../TASKS.md`](../TASKS.md)

### Fase 1 — Seguridad


### Fase 2 — Configuración y CI

- [ ] **CFG-02 — 21 variables leídas y no documentadas** en `.env.example`, entre ellas
  `HUB_INTERNAL_SECRET` (secreto compartido con los 3 dominios, documentado en cero sitios),
  `LEGACY_ADMIN_TOKEN`, `CMS_DOMAIN_URL`, `CATALOG_DOMAIN_URL`, `EVENT_DOMAIN_URL`, `CORS_ALLOWED_*`.
  Resolver además la colisión `FILES_USER_SCOPED` vs `FILE_USER_SCOPED_FILES` (dos banderas
  casi idénticas, ambas declaradas).
- [ ] **CFG-05 — Alinear el gate de calidad** con la política única de la flota (umbral de cobertura
  actual 47,15 %; CI reimplementa el gate como pasos sueltos en vez de invocar `composer quality`,
  y ya divergió: CI corre `composer audit`, `quality` no).
- [ ] **CFG-08 — `ci4-api-scaffolding` está en v1.0.0**, una minor por detrás de los tres dominios
  (cms/catalog v1.1.1, event v1.1.2). Retirar además el paso muerto de CI
  `scripts/ci-strip-local-repos.php`: ninguna app tiene la clave `repositories`.

### Fase 3 — Extracción a `ci4-api-core`

- [x] ~~CORE-02 (parcial)~~ — **2026-08-06:** `app/Traits/Controllers/HasCrudActions.php` resultó
  ser código muerto (byte-idéntico a las copias de cms/catalog/event, pero ningún controlador de
  ninguna de las 4 apps lo usaba — el hub y los dominios necesitan `hasPermission()` por acción,
  algo que el trait no soporta) — eliminado. `PermissionFilter` ya extendía
  `AbstractPermissionFilter` de antes; no se le añadió `superAdminBypassCode()` a propósito — el
  hub es la fuente autoritativa de roles/permisos, no necesita el bypass que los dominios sí (para
  amortiguar cache de introspección desactualizada). PHPStan/tests verdes (716).
- [ ] **CORE-02 (residual) — `AppExceptionHandler`, `AuditRepository`, `MetricModel`,
  `RequestLogModel`, `AuditLogModel`** (sin el `onlyEntities()` que catalog y event sí tienen) y el
  drift de esquema en las migraciones de infra siguen sin base compartida — `core:install` del
  paquete no ayuda porque ya existe una migración con ese nombre de clase en las 4 apps.
- [ ] **CORE-06 — Convención única de permisos.** Coordinar la migración de códigos con los roles ya
  asignados en la BD del hub. **Confirmado fuera de alcance de `ci4-api-core`** — es config local en
  cada dominio más una migración de datos aquí, en `permissions`/`role_permissions`.
  ⚠️ **`domain:sync-permissions` es insert-if-missing, no upsert**: renombrar un código sin una
  migración SQL manual en esta BD deja huérfanas las filas viejas de `permissions` y sus bindings
  en `role_permissions` — los roles pierden el permiso en silencio, no se re-vinculan al código
  nuevo. Requiere ventana de mantenimiento — ver decisiones pendientes.
  **No tocar sin confirmación explícita.**

### Fase 4 — Coherencia de capas

- [ ] **LAYER-02 — 5 controladores se saltan el DTO.** El peor:
  `app/Controllers/Api/V1/Iam/SelfPermissionsController.php` lee JSON crudo (l.46), construye
  401/422/200 a mano (l.42/55/68) e **instancia modelos desde el controlador** (l.61-62).
  También `Internal/InternalFileMetaController.php:52` (`model(FileModel::class)`),
  `Auth/ServiceTokenController`, `Iam/RolePermissionMatrixController`,
  `Identity/PasswordResetController:42`.
- [ ] **LAYER-03 — ~40 sitios de builder crudo en 8 servicios IAM** (`UserRoleAssignmentService` 11,
  `RoleService` 6, `EffectivePermissionsResolver` 5, `RolePermissionAssignmentService` 5,
  `RolePermissionMatrixService` 4, `IamAuthorizationService` 3, `AssignableRolesService` 2,
  `ApplicationPermissionsResolver`). La lista blanca de
  `tests/Unit/Architecture/ServiceModelDependencyConventionsTest.php:36` **ya creció más allá de las
  "seis excepciones justificadas"** que declara su docblock (l.13).
  ⚠️ Decidir primero: modelos para las tablas IAM, o declarar IAM como capa de repositorio con ADR.
- [ ] **LAYER-04 — Faltan los tests de arquitectura** `ControllerModelDependencyConventionsTest` y
  `ArchitectureTest`, que sí existen en cms y catalog. Es decir: la app con violaciones
  controlador→modelo es precisamente la que no tiene el guardián.
- [ ] **LAYER-07 — `app/Services/Auth/TokenIntrospectionService.php` no tiene ningún test**, y es la
  clase de la que depende la frontera de autenticación de los 3 dominios y del BFF. **Cubrirla
  primero.** Sin tests tampoco: `Iam/RolePermissionMatrixService`, `Iam/ApplicationService`,
  `Files/ClamAvScannerService`, `Tokens/Support/ApiKeyMaterialService`. Controladores sin
  referencia en `tests/`: `InternalEmailController`, `InternalFileMetaController`,
  `Iam/ApplicationController`, `Iam/UserPermissionsController`.

### Fase 5 — Migraciones y datos

- [ ] **MIG-02 — Cuatro migraciones cuyo neto es cero:** `CreateAppUserMembershipsTable` +
  `CreateMembershipRolesTable` → `MigrateMembershipRolesToUserRoles` → `DropMembershipsTables`.
  Igual la cadena `CreateRolesTable` → `AddIsSelfAssignableToRoles` → `DropApplicationIdFromRoles`.
- [ ] **MIG-03 — `app/Database/Seeds/UsersLoadTestSeeder.php`** es un generador con faker para
  pruebas de carga viviendo en el directorio de seeds de producción. Mover a `tests/` o eliminar.
- [ ] **DATA-01 — Construir `php spark files:audit`** (solo reporta, **no borra**). Hoy no existe
  ninguna forma de saber qué está huérfano en los 9.110 archivos / 2,0 GB de `writable/uploads`
  (1.811 son variantes regenerables vía `RegenerateFileVariants`). Tres listas:
  archivos en disco sin fila en `files`, filas sin archivo en disco, y filas que ningún dominio
  referencia (consultando los `FileUsageService` de cms/catalog/event vía endpoint interno).
- [ ] **HYG-01 — Purgar y rotar `writable/debugbar` (1,4 GB) y `writable/logs` (49 MB).**
  Verificar que el toolbar solo escribe en `CI_ENVIRONMENT=development`.

### Fase 6 — Limpieza y docs

- [ ] **DEAD-02 — `app/Config/Routes/v1/public.php:24` sigue siendo la plantilla intacta**
  (*"Replace the ping example below with the real public endpoints for your app"*), seguida de una
  única ruta `public/ping` en closure. Toda la superficie pública del hub con `appKeyRequired` es un
  marcador de posición.
- [ ] **DOC-01 — Corregir el puerto 8080 en `CLAUDE.md`** (el hub corre en 8180) y crear el
  `AGENTS.md` que falta en este repo.

## ✅ Completadas

- **CORE-02 (parcial) — `ci4-api-core` v1.2.0 → v1.3.0, `HasCrudActions` muerto eliminado
  (2026-08-06):** subida directa (constraint `^1.0`, sin tocar `composer.json`), incluyó el fix del
  CVE alto de `guzzlehttp/guzzle` (7.15.1 → 7.15.2, `CVE-2026-69246`/`CVE-2026-69245`) de paso.
  `app/Traits/Controllers/HasCrudActions.php` eliminado: byte-idéntico a las copias de cms/catalog/
  event, sin un solo consumidor real en ninguna de las 4 apps. Verificado: PHPStan sin errores,
  716 tests / 1.995 assertions ✅.

- **CFG-01 — Puerto canónico del hub (2026-08-05):** `.env.example`, `.env.docker.example`,
  PHPUnit, Compose, CORS de desarrollo y `init.sh` ahora usan `8180`; el bootstrap pasa el puerto
  explícitamente a `php spark serve`. Composer quality ✅.

- **SEC-03 — Sustituir el escáner antivirus simulado (2026-08-05):** `ClamAvScannerService` fue
  reemplazado por `NullVirusScannerService`; con el flag apagado registra que el archivo no fue
  escaneado y con el flag encendido lanza una excepción fail-closed. Se añadieron mensajes i18n,
  configuración explícita y 2 tests unitarios. PHPStan, CS-Fixer, Swagger y los tests dirigidos ✅.
  La suite completa conserva 7 errores preexistentes en `LegacyApplyServiceTest` por falta de
  configuración CMS (`teatroescuela`), sin relación con este cambio.

- **LEGACY-MAP-033 — Regla complementaria: la portada también debe existir como primer ítem de
  la galería, sin duplicar imágenes (2026-08-02):** Segunda mitad de la regla pedida por David en
  LEGACY-MAP-032 — "de esta manera estaríamos repitiendo la misma imagen en la portada y como
  primer item de la galería". Para `sn_escuela` (Cursos Históricos) esto ya queda satisfecho por
  construcción (LEGACY-MAP-032 copia la portada DESDE el primer ítem de galería, así que siempre
  son el mismo archivo). El caso pendiente era `sn_cursos` (Cursos Actuales): tiene su propia
  portada (`image_cover`) pero **ninguna tabla de galería propia** — su portada no aparecía en
  ninguna galería.
  - **Fix en el ETL:** `applyCurrentCourses()` extrae el `coverFileId` ya resuelto a una variable
    y, si no es null, construye una galería sintética de un solo ítem a partir de esa MISMA
    portada y la pasa por `applyGallery()`. Para evitar una re-subida duplicada del archivo,
    `imageTable()` gana un caso nuevo `'sn_cursos' => 'sn_cursos'` — así el lookup de
    `(legacyTable, legacyId)` que hace `assetFile()` dentro de `applyGallery()` cae exactamente
    sobre el mismo `legacy_migration_map` que ya registró la portada, reutilizando el mismo
    file id en vez de subirlo de nuevo. Nueva clave genérica `sort_position` (con prioridad sobre
    `escuela_img_posicion`/`id_slider`) en el cálculo de `sort_order` de `applyGallery()`, fijada
    en `1` para este ítem sintético — así queda garantizado en primera posición
    independientemente del `courseId`.
  - **Verificado:** nuevo test
    `testCurrentCourseCoverIsAlsoAddedAsGalleryFirstImageWithoutDuplicateUpload` (14 tests en el
    archivo, todos ✅) — comprueba que el gallery_item creado referencia el mismo file id que la
    portada (no un id distinto de una re-subida), que su `sort_order` es 1, y que una segunda
    corrida no crea un ítem duplicado. `composer quality` ✅ (695 tests, 2 skips preexistentes).
    En vivo: `legacy:apply --slice B` creó 40 bloques nuevos (galería + ítem × 20 cursos
    actuales), **0 archivos nuevos** (confirma que no hubo re-subida), 123 entries reusadas.
    Confirmado en las 20 entradas de `sn_cursos` que el único ítem de su galería usa el mismo
    file id que su portada, y verificado visualmente en
    `/es/cursos/el-que-la-sigue-la-consigue-creaciones-2026-c44` que la imagen de portada y la
    primera miniatura de galería son literalmente el mismo archivo.

- **LEGACY-MAP-032 — Regla: portada de curso histórico = copia de la primera imagen de su
  galería (2026-08-02):** David revisó el paginador completo de `/es/cursos` (6 páginas) y
  reportó varios cursos sin portada; pidió una regla robusta (no un parche puntual): la portada
  de un curso siempre debe ser una copia de la primera imagen de su galería. Confirmado contra el
  dump: `sn_escuela` ("Cursos Históricos") **no tiene ningún campo de portada propio** — la
  galería es la única fuente posible. Diagnóstico previo: 48/68 entradas de cursos sin portada,
  las 48 correspondían exactamente a las 48 entradas `sn_escuela`; de esas, 44 sí tenían galería
  (arregladas por esta regla) y 4 no tenían ni portada ni galería (sin fuente de la cual copiar,
  quedan igual).
  - **Fix en el ETL (`LegacyApplyService::applyCourses()`):** las imágenes de
    `sn_escuela_img` ahora se ordenan por `escuela_img_posicion` (con `escuela_img_id` como
    desempate, nulls al final) **antes** de pasarlas a `applyGallery()` — necesario porque el
    array de la tabla no viene en orden de visualización, y el bug original hubiera copiado
    "el primer elemento del array" en vez de "el primer elemento como se muestra". Tras aplicar
    la galería, si `applyGallery()` devolvió al menos un file id, se llama a
    `reconcileFeaturedImage()` (mecanismo ya existente de LEGACY-MAP-028, con su propia clave de
    mapeo `:cover` para idempotencia) con el primer file id — mismo patrón ya probado, no un
    camino nuevo. Se ejecuta en cada corrida, no solo cuando falta portada: si la primera imagen
    de la galería cambia alguna vez, la portada se resincroniza sola.
  - **Verificado:** nuevo test
    `testCourseCoverIsCopiedFromFirstGalleryImageByDisplayPosition` (13 tests en el archivo,
    todos ✅) — construye deliberadamente la galería en orden inverso de posición para probar
    que se ordena antes de elegir, y verifica que una segunda corrida no vuelve a hacer `PUT`.
    `composer quality` ✅ (694 tests, 2 skips preexistentes). En vivo: `legacy:apply --slice B`
    creó 44 archivos nuevos (0 entries nuevas, 123 reusadas) — cobertura de portadas en `cursos`
    pasó de 20/68 a 64/68; los 4 restantes son exactamente los 4 sin galería (`Creación de
    Números Cómicos y de Títeres` ×3, `Comicidad`), confirmados en vivo en la página 6 del
    paginador.

- **LEGACY-MAP-031 — sn_cursos y sn_escuela son tablas independientes, no
  base+suplemento (supera a LEGACY-MAP-030) (2026-08-02):** David pidió comparar
  http://localhost:8184/es/cursos contra la base legacy y reportó que las portadas puestas por
  LEGACY-MAP-029 "no coinciden"; luego confirmó explícitamente: *"los id de escuela y los de
  cursos aunque sean el mismo no coinciden con ser el mismo curso"*. Investigación exhaustiva:
  se cruzaron los 20 pares con id coincidente entre `sn_escuela.curso_id` y `sn_cursos.id` —
  **100% de mismatch de fechas** (`sn_escuela` 2017-2021 vs `sn_cursos` 2024-2026, brechas de
  hasta 5 años) y títulos/temas sin relación. `sn_cursos` no tiene ninguna columna FK hacia
  `sn_escuela` (`CREATE TABLE` confirmado: `id, title, image_cover, date_start, date_end,
  description_text, pdf_file, google_forms_link, contact_email, youtube_video_link, display,
  category_id` — sin `curso_id`/`escuela_id`). Son dos sistemas legacy independientes que
  reflejan el propio menú del sitio real: `sn_escuela` = "Cursos Históricos" (53 filas),
  `sn_cursos` = "Cursos Actuales" (20 filas, confirmado 1:1 contra
  `https://teatromuseo.cl/teatroescuela` → "Items encontrados: 20"). El id numérico compartido
  (ambas tablas usan el rango 25-44) es coincidencia de dos auto-increments independientes. El
  fix de LEGACY-MAP-030 (detectar título duplicado) solo tapó el síntoma para 7 de los 20 casos
  con id coincidente — los otros 13 tenían título/portada/descripción igual de mal atribuidos,
  solo que sin duplicarse, así que la heurística de duplicados no los detectaba.
  - **Fix en el ETL (`LegacyApplyService::applyCourses()`):** eliminado por completo el join
    falso por id. `sn_escuela` ahora usa únicamente sus propios campos (título, descripción,
    fechas, portada — nunca toca `sn_cursos`). Nuevo método privado `applyCurrentCourses()`
    migra las 20 filas de `sn_cursos` como sus propias entradas CMS independientes en la misma
    colección `cursos`, con `legacy_table='sn_cursos'` como fuente primaria (no suplemento) —
    slug derivado de `slug(title) . '-c' . id` para evitar colisión entre cursos con título
    real duplicado (ej. 3 cursos "Súbete al Escenario" distintos). Reusa el mismo lookup de
    `sn_categoria_escuela` (confirmado: mismo catálogo de categorías para ambas tablas —
    1=Nacional, 2=Internacional, 3=Para Niños).
  - **Corrección de datos en vivo:** los 19 entries de `sn_escuela` ya contaminados (título,
    excerpt y portada tomados del `sn_cursos` mal emparejado por id) corregidos vía `PUT
    /cms/entries/{id}` — título y descripción restaurados a los campos propios de `sn_escuela`,
    portada puesta en null (4 idiomas c/u). Los 19 mapeos obsoletos
    `sn_cursos→entry(status=supplemental)` en `legacy_migration_map` borrados (si no,
    `applyCmsEntry()` habría reusado la entrada incorrecta en vez de crear una nueva). Re-corrido
    `legacy:apply --slice B --confirm`: 20 `cms_entries` creados (las 20 entradas nuevas de
    `sn_cursos`), 103 reusados, 1 archivo nuevo. Las 20 entradas nuevas se crean en `draft` por
    diseño de `applyCmsEntry()` — publicadas manualmente vía `PUT` (`workflow_status=published`)
    tras confirmar contenido; caché de `teatromuseo-web` invalidada (`POST /cache/invalidate`).
  - **Verificado:** `composer quality` ✅ (693 tests, 2 skips preexistentes) tras reescribir 2
    tests de `LegacyApplyServiceTest` (`testSliceBSecondPassReusesHistoricalAndCurrentCourseEntriesIndependently`,
    `testHistoricalCoursesNeverInheritDataFromCoincidentallyIdMatchedCurrentCourses`) que
    prueban que ambas tablas se migran sin cruzarse y que un título real duplicado dentro de
    `sn_cursos` no colisiona de slug. En vivo: verificado en `localhost:8184/es/cursos` (grid +
    detalle) que las 20 entradas actuales muestran su propio título/fecha/categoría/portada, y
    que las 53 históricas ya no tienen datos de `sn_cursos` — cruzado un curso actual
    ("El que la sigue... La consigue - Creaciones 2026", fecha 2026-08-04, categoría Nacional)
    contra `https://teatromuseo.cl/teatroescuela` en vivo, coincidencia exacta.
  - **Nota de proceso:** durante la corrida se agotó el rate limit del hub
    (`API_KEY_RATE_LIMIT_DEFAULT`, 600/60s) por el volumen de resolución de `featured_image` en
    un listado sin filtrar de +300 entries — subido temporalmente a 6000 solo para la corrida
    (revertido a 600 después). Considerar si `GET /cms/entries` sin filtro debería paginar la
    resolución de imágenes en vez de resolverlas todas de una vez.

- **LEGACY-MAP-030 — Título duplicado entre cursos distintos (bug de datos legacy, no de
  migración) (2026-08-02):** David reportó ver "galerías que no corresponden" en cursos —
  imágenes de un curso apareciendo bajo el nombre de otro. Investigación: las galerías
  estaban correctamente vinculadas (verificado cruzando `sn_escuela_img.curso_id` contra el
  dump real — cada imagen pertenece a un solo curso, sin colisión). El problema real: 7 filas
  de `sn_cursos` (la tabla suplementaria, normalmente más confiable que `sn_escuela.curso_titulo`
  para el título público) tienen un título repetido literalmente entre varios cursos distintos
  — 5 cursos comparten "Súbete al Escenario" (curso_id 25/27/32/36/40, cada uno con su propio
  `curso_titulo` correcto: "The Logic of Movement", "La Divina Escuela de Bufones", "El Desvelo
  II", "Taller de Autómatas", "La Ventana Mágica") y 2 comparten "La Escuela de los Nuevos
  Comediantes" (curso_id 34 —no visible, `curso_display=0`, sin impacto real— y 41,
  "Arquetipos de los Cómicos"). Con el mismo título repetido y galerías/fechas distintas, se
  leía como si las imágenes estuvieran "cruzadas" entre cursos.
  - **Fix en el ETL:** `applyCourses()` ahora cuenta cuántos cursos distintos comparten el
    mismo `sn_cursos.title` antes de usarlo; si es 1, se usa (como antes, sigue siendo más
    confiable que `curso_titulo` en general); si es 2+, se descarta como duplicación y se usa
    el `curso_titulo` de `sn_escuela`, específico por curso. Corre a prueba de un futuro
    refresh del dump — no depende de una lista de excepciones hardcodeada.
  - **Corrección en vivo:** los 6 cursos ya migrados con título incorrecto (curso_id 34 no
    aplica, nunca se migró) corregidos vía `PUT /cms/entries/{id}` (título en los 4 idiomas —
    el ETL nunca tradujo de verdad, solo replicó el mismo string). `legacy:apply --slice B`
    re-corrido después: 0 creados, todo reusado, confirmado que los títulos corregidos
    persisten (las rutas de reuso del ETL no tocan el título, por diseño — la única vía para
    corregirlos ya migrados es una corrección directa).
  - **Verificado:** nuevo test `testCourseFallsBackToBaseTitleWhenSupplementTitleIsDuplicatedAcrossCourses`
    (12 tests en el archivo, todos ✅), `composer quality` ✅ (693 tests, 2 skips
    preexistentes). En vivo: 0 cursos con título "Súbete al Escenario" a secas, los 6 títulos
    corregidos presentes y verificados uno por uno vía la API pública.

- **LEGACY-MAP-029 — Portadas de cursos: assets nunca descargados al asset-root (2026-08-02):**
  David pidió revisar qué había pasado con las portadas/galería de la colección `cursos` (0/48
  con portada). A diferencia de LEGACY-MAP-028 (bug de código), aquí la causa fue de datos: los
  20 `sn_cursos.image_cover` reales apuntaban a `/images/escuela/*.png|.JPG`, ausentes del
  asset-root local (`teatromuseo_webapp_php/`) desde la preparación original (LEGACY-MAP-022)
  — confirmado con `LegacyAssetResolver` contra el dump real (20/20 `status=missing`). Los 20
  archivos seguían disponibles en `https://teatromuseo.cl/images/escuela/` (200 OK, verificado
  uno por uno) — descargados con el mismo criterio de autorización que LEGACY-MAP-022. Re-corrida
  `legacy:apply --slice B --confirm` contra cms-domain: portadas de cursos 0→19/48 (el resto
  genuinamente no tiene `image_cover` en el dump — no un bug). Idempotencia confirmada con una
  segunda corrida (0 archivos nuevos). El fix de orden de la colección (próximos primero, luego
  descendente) se implementó en `teatromuseo-cms-domain` — ver su `TASKS.md` (CURSOS-001).

- **LEGACY-MAP-028 — Portadas faltantes en obras/eventos: bug de reconciliación tras
  upload fallido (2026-08-01):** David preguntó si las imágenes de la Cartelera (portadas,
  galerías) estaban funcionando tras la publicación de LEGACY-MAP-027. Auditoría directa en BD
  reveló que solo 100/369 `obras` tenían `featured_file_id` pese a que 366/369 de sus archivos
  ya estaban subidos y mapeados en `legacy_migration_map` (confirmado: los 638 `sn_obra`
  visibles tienen `foto_obra` no vacío — no es un gap de datos legacy). Causa raíz en
  `applyCmsEntry()`: sus tres ramas de "la entrada ya existe, reusar" (via `findMap()`, via
  slug+colección, via colisión de slug global) nunca intentaban adjuntar un `$featuredFileId`
  recién resuelto — solo la ruta de creación lo hacía. Si el upload de portada fallaba en una
  corrida (rate limit de LEGACY-MAP-024) pero la entrada ya se había creado, una corrida
  posterior con el archivo ya disponible encontraba la entrada vía el early-return y descartaba
  el `featuredFileId` en silencio. Fix: nuevo método `reconcileFeaturedImage()` (GET entrada →
  PUT solo si el `file_id` actual difiere → registra mapa `:cover` para idempotencia) invocado
  desde las tres ramas de reuso. Requirió agregar `put()` a `LegacyDomainClientInterface` +
  `LegacyHttpDomainClient` (hereda el backoff-on-429 existente) + los 2 fakes de test.

  **Segundo hallazgo, mismo día — verificación en navegador reveló que el fix de arriba no
  bastaba:** la Cartelera pública lee `events.cover_file_id` (event-domain), no el
  `featured_image` de la entrada CMS — campos completamente independientes.
  `applyEvent()` nunca seteaba `cover_file_id` en ningún run (0/381 eventos con portada,
  confirmado vía API pública). Mismo patrón de fix: `cover_file_id` ahora viaja en el POST de
  creación, y un nuevo `reconcileEventCover()` (PUT directo, sin GET previo — a diferencia de
  las entradas CMS, `cover_file_id` no tiene estructura por idioma que preservar) cubre las dos
  ramas de reuso de `applyEvent()`, con su propio mapa `:event-cover` para idempotencia.

  **Resultados reales (`legacy:apply --slice A/B/C --confirm` contra cms-domain/event-domain
  productivos, no dry-run):** obras 100→365/369 portadas (+265, vía 70 uploads nuevos + ~195
  reconciliaciones de archivos ya subidos), personas 6→10/67 (+4), publicaciones 5→15/47 (+10),
  eventos 0→366/381 (+366). Sin duplicados (`created.cms_entries`/`created.events` en 0 en la
  corrida de reconciliación). Cursos (0/50), compañías (0/214) y videos (0/46) quedan sin
  cambios — confirmado que no es el mismo bug: sus assets fuente están genuinamente ausentes o
  no resueltos (`asset_missing`/`file_not_found`), un gap de datos preexistente y separado, no
  el bug de reconciliación. Idempotencia verificada con una segunda corrida real (0 archivos
  creados, 0 PUTs nuevos). Verificado visualmente en `http://localhost:8184/cartelera` —
  imágenes reales cargando desde el hub (200 OK, `image/jpeg`).

  **Tercer hallazgo, mismo día — David confirmó continuar con las galerías también:**
  `events.gallery_file_ids` (columna CSV plana, sin equivalente de bloques CMS) tenía el mismo
  problema: 0/381 eventos con galería, aunque las imágenes ya estaban resueltas y adjuntas como
  bloques `gallery_item` en el lado CMS vía `applyGallery()`. A diferencia de la portada,
  `applyEvent()` se ejecuta *antes* de que `applyGallery()` resuelva las imágenes de la obra, así
  que nunca pudo setearse en el POST de creación — `applyGallery()` ahora retorna la lista de
  `file_id`s que resolvió (antes era `void`; se reordenó para llamar `assetFile()` siempre,
  incluso cuando el bloque `gallery_item` ya existía, en vez de saltarlo) y un nuevo
  `reconcileEventGallery()` la adjunta vía PUT como CSV incondicionalmente después de cada
  corrida (funciona tanto si el evento se acaba de crear como si se reusó), con su propio mapa
  `:event-gallery` — si se agrega una imagen nueva en el legacy más adelante, el CSV cambia y se
  re-sincroniza solo.

  **Resultados reales (misma corrida en vivo):** eventos con galería 0→261/381. Idempotencia
  confirmada con una segunda corrida real (0 archivos nuevos, 0 PUTs nuevos). Verificado
  visualmente en `http://localhost:8184/es/cartelera/la-ciscu-margaret-variete-de-payasos` —
  portada + 2 imágenes de galería cargando desde el hub (200 OK, `image/jpeg`).

  15 tests nuevos/extendidos en `LegacyApplyServiceTest.php` en total (reconciliación de entrada
  CMS + portada de evento + galería de evento, las tres cubiertas en el mismo test de 3
  corridas), `composer quality` ✅ (692 tests, 2 skips preexistentes, dos corridas separadas —
  una por cada hallazgo).

- **LEGACY-MAP-027 — Publicar el contenido migrado: draft/scheduled → published
  (2026-08-01):** Toda la migración legacy (022-026) creaba contenido en estado no-público por
  diseño (`workflow_status: draft` en `applyCmsEntry()`, `status: scheduled` en `applyEvent()`)
  — correcto para no auto-publicar legacy sin curar, pero significaba que nada de lo migrado era
  visible en el sitio. Confirmado con David: publicar todo sin revisión previa por colección.
  Sin endpoint bulk disponible; script puntual (no versionado, fuera del ETL) que pagina
  `/cms/entries` y `/events/events` (100 por página) y hace PUT individual por fila a
  `workflow_status`/`status`. `cms_pages` no tiene gate de draft/publish (las páginas quedan
  visibles apenas se crean) y las `occurrences` de event-domain no se tocaron — su `status`
  describe si la función está programada/cancelada/realizada, no es un gate de visibilidad
  pública (ese vive en `events.status`). Ejecutado: **877/877 `cms_entries` y 381/381 `events`
  publicados, 0 fallos**, verificado directo en ambas bases de datos.

- **LEGACY-MAP-026 — Sliders no-home: Quienes Somos, Historia, Upa Chalupa, Anímate
  (2026-08-01):** El conteo original ("494 de 499 filas de `sn_slider` sin migrar") estaba mal —
  incluía basura histórica con `display=0`. Las filas **visibles** reales son solo 8: 3 en
  categoría 2 (Quienes Somos), 2 en categoría 3 (Historia), 2 en categoría 4 (Upa Chalupa), 1 en
  categoría 5 (Anímate). Decisiones de David:
  1. **Quienes Somos / Historia**: agregar un `hero_slider` nuevo (mismo bloque que usa el home),
     justo después del `page_header`, en vez de forzar el contenido en los bloques ya existentes
     (`cards_slider` de testimonios reales, `asset_showcase` de logos institucionales, `image`
     único con caption) — esos ya tienen contenido deliberado, no genérico, que no debía
     tocarse. Contenedores `hero_slider` creados una sola vez, directo vía API contra cms-domain
     (edición de estructura de página puntual, no parte del ETL versionado — mismo patrón que
     LEGACY-MAP-020's fix de `cta_url`), reordenando los bloques existentes para dejarles espacio.
  2. **Upa Chalupa / Anímate**: son festivales, no páginas — sus imágenes se agregan como galería
     a las entradas de `festivales` ya migradas (`upa-chalupa-2019`, `animate-2024`), mismo
     mecanismo que obras/cursos.
  Código: `applyHomeSliderSlides()` generalizado a `applySliderSlides(tables, runId, pageId,
  categoria)`, llamado para las 3 categorías de página; nuevo `applyFestivalSliderGallery()` para
  las 2 categorías de festival. 2 bugs reales encontrados y corregidos en el camino:
  1. **`imageTable()` mal namespacing `sn_slider`**: el helper que decide bajo qué tabla legacy
     se registra un `gallery_item` en el control-plane solo conocía `sn_obra`→`sn_slider_cartelera`
     y todo lo demás→`sn_escuela_img` — las imágenes de festival habrían quedado mal etiquetadas
     y en riesgo de colisión de ID con `sn_escuela_img`. Agregado un caso explícito para
     `sn_slider`.
  2. **`findCmsEntry()` solo veía la primera página de `/cms/entries`**: cms-domain limita
     `per_page` a 100 en el servidor sin importar lo pedido (`EntryIndexRequestDTO` permite hasta
     1000, algo más abajo lo recorta) — con 877 entradas reales tras la migración completa, la
     entrada de Anímate (más allá de la página 1) no se encontraba, generando un falso
     `target_missing`. Nuevo `listAllPages()` que recorre todas las páginas usando
     `meta.last_page`; `findCmsEntry()` ahora lo usa. Este bug pudo afectar en silencio la
     detección de duplicados de cualquier slice anterior una vez pasadas las primeras 100
     entradas — el chequeo primario de idempotencia (`legacy_migration_map`) nunca dependió de
     esto, así que no generó duplicados reales, pero el mecanismo de recuperación secundario
     (`findCmsEntry`) sí estaba roto para colecciones grandes.
  3 tests nuevos (slides de página con exclusión correcta de categoría/visibilidad, degradación
  correcta cuando falta la entrada de festival, paginación completa de `findCmsEntry`).
  Ejecutado contra el dump real y cms-domain: 3 slides en Quienes Somos, 2 en Historia, galería
  de 2 fotos en Upa Chalupa, galería de 1 foto en Anímate — verificado directo en
  `cms_block_instances`. Idempotencia confirmada en corridas repetidas. `composer quality` ✅
  (691/691 tests, 2 skips preexistentes).

- **LEGACY-MAP-025 — Migración completa Slice C: noticias y publicaciones (2026-08-01):**
  Quitados `array_slice($newsRows, 0, 20)` en `applyNoticias()` y `$pubLimit = 30` en
  `applyPublicaciones()` (junto con el tracking de `$pubCount`, ya innecesario), y sus
  equivalentes en `LegacyDryRun.php` (`PHP_INT_MAX` para `newsLimit`/`pubLimit` en
  `LegacySliceCAnalyzer::analyze()`; `expoLimit`/`staffLimit` sin cambios, ya cubrían el 100% de
  sus tablas). Sin bugs nuevos — el patrón de "relleno" que afectó a compañías en
  LEGACY-MAP-024 no existe aquí, ambos métodos ya seleccionaban solo filas visibles reales. Fila
  basura ya conocida en `sn_administracion` (id=6, "test") resultó tener `display=0`, así que el
  filtro de visibilidad existente ya la excluía sin necesidad de código nuevo. 1 test de
  regresión de escala. Ejecutado contra el dump real y cms-domain: **136 entradas** (69 noticias
  + 49 publicaciones + 6 exposiciones + 10 personas + 2 festivales, ya completos de antes), 15
  issues (mismo patrón conocido de tamaño/tipo de archivo), idempotencia confirmada (segunda
  corrida: 0 creadas, 136 reusadas). Verificado en `cms_entries`: `noticias` en 70,
  `publicaciones` en 47. `composer quality` ✅ (688/688 tests, 2 skips preexistentes).

- **LEGACY-MAP-024 — Migración completa Slice A: obras, compañías, galería y videos
  (2026-08-01):** Quitados los 3 límites hardcodeados en `applyWorks()` (10 obras, 3 compañías,
  5 videos) y sus equivalentes en `LegacyDryRun.php` (ahora pasa `PHP_INT_MAX` a
  `LegacySliceAAnalyzer::analyze()`). Implementada la exclusión de las 2 filas basura de
  `sn_obra` ("Test"/"TEst") confirmada en LEGACY-MAP-022, en `applyWorks()` y en el analyzer.
  Encontrados y corregidos 2 bugs reales en el camino:
  1. **Rate limiting sin manejar**: el hub limita `/files/upload` a 60 req/60s; con ~1400 subidas
     en ráfaga la mayoría (1152) chocó con HTTP 429 y quedó registrada como `asset_rejected`
     permanentemente. `LegacyHttpDomainClient::request()` ahora reintenta automáticamente hasta
     3 veces en cualquier 429, respetando `retry_after` del cuerpo de la respuesta (o el header
     `Retry-After`, o 60s por defecto) — no solo en `/files/upload`, en cualquier request. Con el
     fix, una segunda corrida bajó los issues de 1152 a 11 (todos legítimos: tamaño/tipo de
     archivo, mismo patrón ya documentado en LEGACY-MAP-016/019 — decisión de `FILE_MAX_SIZE`
     sigue sin tomarse unilateralmente).
  2. **`LegacySliceAAnalyzer` sobre-reportaba compañías**: tenía el mismo patrón de "relleno"
     (rellenar hasta `companyLimit` con compañías arbitrarias no referenciadas) que ya se había
     quitado de `applyWorks()`, pero no del analyzer — con el límite en `PHP_INT_MAX` esto hacía
     que planeara las 230 compañías completas en vez de solo las realmente referenciadas por
     obras visibles. Los datos reales nunca estuvieron mal (`apply()` siempre fue correcto, solo
     creó compañías con al menos una obra visible que las referencia); era el reporte de
     dry-run el que mentía. Corregido: el analyzer ahora solo planea compañías efectivamente
     referenciadas, igual que `applyWorks()`. 2 tests nuevos de regresión (rate-limit retry +
     no-padding de compañías) más 1 test de escala/exclusión de basura para `applyWorks()`.
  Ejecutado contra el dump real y cms-domain: **625 entradas** (369 obras + 214 compañías + 46
  videos, verificado directo en `cms_entries`), 368 eventos, 638 ocurrencias, 1111+ items de
  galería, idempotencia confirmada en corridas repetidas (0 creadas, todo reusado). `composer
  quality` ✅ (687/687 tests, 2 skips preexistentes).

- **LEGACY-MAP-023 — Migración completa Slice B: cursos y profesores (2026-08-01):** Quitados
  los límites hardcodeados en `LegacyApplyService::applyCourses()` (`array_slice($courses, 0, 3)`
  y `count($teachers) >= 20`), y los defaults del analyzer para el dry-run (`LegacyDryRun.php`
  ahora pasa `PHP_INT_MAX` a `LegacySliceBAnalyzer::analyze()`). En el camino: encontrado y
  corregido un bug real en `LegacyAssetResolver::resolve()` — usaba `parse_url()` sobre paths que
  ya eran rutas de archivo (no URLs), y `parse_url()` es byte-oriented, no UTF-8-aware: corrompía
  el segundo byte de la codificación UTF-8 de "í" (0xAD → "_"), lo que después tumbaba
  `json_encode(..., JSON_THROW_ON_ERROR)` del reporte de dry-run con "Malformed UTF-8". Ahora
  solo pasa por `parse_url()` cuando el string realmente tiene esquema (`://`). 1 test nuevo de
  regresión. Ejecutado contra el dump real y cms-domain: **103 entradas** (48 cursos + 55
  profesores, filtrados por `display`, de 53/57 totales), 77 items de galería, 60 archivos
  subidos, idempotencia confirmada (segunda corrida: 0 creadas, 103 reusadas). Verificado en
  `cms_entries`: colección `cursos` en 50 filas, `personas` en 67. `composer quality` ✅
  (684/684 tests, 2 skips preexistentes).

- **LEGACY-MAP-022 — Preparación para la migración completa: asset-root + limpieza de datos
  (2026-08-01):** Auditoría de los 11 campos de asset en las 11 tablas objetivo (`sn_obra`,
  `sn_slider_cartelera`, `sn_escuela_img`, `sn_funcionarios`, `sn_editorial`, `sn_prensa`,
  `sn_administracion`, `sn_noticias`, `sn_expo_img`, `sn_museo`, `sn_slider`): 2,869 paths
  distintos, 2,269 ya presentes localmente, 600 faltantes. De esos, 583 alcanzables en
  `teatromuseo.cl` (con autorización explícita de David, ~1.6 GB) y 17 con enlace roto ya en el
  sitio legacy (nombres con "ñ" mal codificados, HTTP 507/508 — no es algo que se pueda
  descargar). Descargados 553/583 (3 fallos puntuales, 27 con bytes UTF-8 inválidos en el path
  del dump que impiden crear el archivo local — necesitan corrección manual de encoding antes de
  poder resolverse, no crítico). Cobertura final: **2,834/2,869 (98.8%)**. Confirmado con David:
  las 2 filas de `sn_obra` con `titulo_obra` "Test"/"TEst" se excluyen de la migración completa
  (a implementar en LEGACY-MAP-024).

- **LEGACY-MAP-020 — Parchear bug de `ci4-api-core`: update solo-translations rechazado
  (2026-08-01):** Fix en la fuente del paquete (`fix(services): don't reject update() when
  beforeUpdate() defers all fields`), released v1.1.1. `BaseCrudService::update()` ahora revisa
  `empty($data)` sobre el payload crudo antes de `beforeUpdate()` (no después), y salta el
  `repository->update()` directo — sin abortar el flujo — cuando `beforeUpdate()` deja todo
  diferido; `afterUpdate()` siempre corre. Verificado en vivo contra `teatromuseo-cms-domain`
  antes del release. `teatromuseo-api`, `teatromuseo-cms-domain`, `teatromuseo-catalog-domain`,
  `teatromuseo-event-domain` y `teatromuseo-bff` actualizados a v1.1.1 (`composer update`, suite
  completa + PHPStan verde en cada uno; `teatromuseo-api` requirió corregir la firma de
  `FakeApiKeyRepository::findAll()` al `?int $limit = null` de la 1.1.0, que nunca había
  adoptado).

- **LEGACY-MAP-018 — Decidir páginas TeatroEscuela y Anímate (2026-08-01):** David decidió
  ambas partes directamente:
  1. **TeatroEscuela**: sin migrar. `sn_banner` (1 fila, "becas teatroescuela"), `sn_section`
     ids 1-2 (`teatroescuela`/`teatroescuela-historico`) y `sn_page_description` id=10 quedan
     explícitamente superados por el módulo Cursos ya existente — no había nada más que hacer.
  2. **Anímate**: es uno de los festivales de la fundación, no una obra suelta. El legacy solo
     tiene **un** registro real (`sn_obra` id=692, url=`animate`: IX Encuentro Internacional de
     Títeres Anímate, 2024-11-02) — nunca se había migrado (no estaba en `legacy_migration_map`,
     fuera de la ventana de los primeros 10 `id_obra` que procesa Slice A). Se migra ahora como
     el segundo item de la colección `festivales` (mismo patrón plano que "Upa Chalupa" — sin
     jerarquía padre/hijo; cada versión futura del festival será, igual que esta, un item más de
     la colección), no como `obras`. Slug `animate-2024` (misma convención año que
     `upa-chalupa-2019`, confirmado con David). Nueva rama en `LegacySliceCAnalyzer` +
     `LegacyApplyService::applyFestivales()` que filtra `sn_obra` por `url='animate'`; `sn_obra`
     agregado a las tablas de Slice C (`legacy:dry-run`/`legacy:apply`) sin tocarlo en Slice A.
     Ejecutado contra el dump real y cms-domain corriendo: 1 entrada nueva creada (id=141,
     colección `festivales`, slug `animate-2024`), las 67 entradas previas de Slice C
     reutilizadas sin cambios (idempotencia confirmada en segunda corrida: 0 creadas, 68
     reusadas). La imagen de portada (`foto_obra`) no se pudo resolver contra el `asset-root`
     local (subida a producción después del último snapshot, mismo patrón que LEGACY-MAP-021) —
     entrada creada sin imagen inicialmente. `composer quality` ✅ (682/682 tests, 2 skips
     preexistentes). **Seguimiento (2026-08-01):** con autorización explícita de David, se
     descargó `2-nov-6722a7e615889.png` desde `https://teatromuseo.cl` (906 KB, confirmada
     pública) al `asset-root` local, se subió al hub (`POST /files/upload`, file id=147,
     registrado en `legacy_migration_map` como `sn_obra:692 → api:file:147` para trazabilidad y
     evitar re-subidas en un futuro re-run), y se actualizó `cms_entries` id=141 en cms-domain
     con `featured_image` apuntando a ese archivo en los 4 idiomas. El update de solo-traducciones
     hubiera chocado con el bug de LEGACY-MAP-020 (`ci4-api-core`); se evitó incluyendo
     `sort_order` (mismo valor, sin cambio real) en el mismo payload — mismo workaround usado en
     el fix del home slider. Verificado con GET: las 4 traducciones resuelven `featured_image`
     con `source_kind=hub_file` y variantes (lg/md/sm/thumb) generadas.

- **LEGACY-MAP-017 — Migrar `sn_contact_message` (157 filas de PII) (2026-08-01):** David
  confirmó: migrar el histórico completo (nombre/email/teléfono/mensaje reales), sin
  retención/expiración automática. El endpoint público `POST public/submissions` no servía —
  siempre pisa `created_at`=ahora y `status`=new — así que se agregó `POST
  /api/v1/cms/submissions/import` en cms-domain (`FormSubmissionImportRequestDTO` +
  `FormSubmissionService::import()`, gateado por el permiso ya existente
  `cms.submissions.write`), que preserva `created_at`/`status` reales (inserta vía el modelo y
  luego corrige `created_at` con `$this->model->builder()->update()`, porque `useTimestamps`
  siempre pisa el valor en `insert()`/`update()` normal) y salta CAPTCHA/emails de notificación
  (no tiene sentido re-notificar hoy sobre un mensaje de 2024). Nuevo slice D en el motor de
  migración: `LegacySliceDAnalyzer` (dry-run) + `LegacyApplyService::applyContactMessages()`
  (apply), wireados en `legacy:dry-run`/`legacy:apply --slice D`. Mapeo: `status_id`
  1=PENDIENTE→`new`, 2=COMPLETADA→`replied` (las 157 filas reales del dump son todas
  COMPLETADA); `ip_address`/`user_agent` se preservan cuando el legacy los tenía (31/157 filas).
  Ejecutado contra el dump real y cms-domain corriendo: 157 creadas, 0 issues, segunda corrida
  idempotente (0 creadas, 157 reusadas vía `legacy_migration_map`). Verificado directamente en
  `teatromuseo_cms_domain.cms_form_submissions`: 157 filas, `created_at` real preservado
  (mín. 2024-07-11, no la fecha de import), encoding UTF-8 correcto, ip/user_agent presentes en
  las 31 filas que los tenían. `composer quality` ✅ en ambos repos (api: 680/680 tests, 2 skips
  preexistentes; cms-domain: 522/522 tests, 1 skip preexistente).

- **LEGACY-MAP-021 — Limpiar el home hero slider: demo fuera, URLs internas, imágenes reales (2026-08-01):**
  David probó el home real y encontró slides sin imagen y mezcladas con contenido de demo del
  starter kit. Dos hallazgos y correcciones: (1) mi reporte anterior decía "2 de 5 imágenes
  faltantes" — error de conteo, eran **las 5**; corregido descargando las 5 desde
  `https://teatromuseo.cl` (con autorización explícita) y subiéndolas al hub. (2) Las 3 slides
  de demo del starter (`picsum.photos`, "Bienvenidos a TeatroMuseo" etc.) se eliminaron; las 5
  reales tenían `cta_url` apuntando al dominio legacy (`https://teatromuseo.cl/...`) — nuevo
  `LegacyApplyService::mapLegacySliderLink()` traduce cada path legacy a su ruta interna real
  en `teatromuseo-web` (`cartelera→/cartelera`, `teatroescuela→/cursos`, etc., fallback a
  `/contacto` para paths sin equivalente claro, nunca deja una URL externa). Datos ya migrados
  corregidos a mano con el mismo mapeo. Encontrado de paso un bug real en el paquete vendored
  `ci4-api-core` (ver LEGACY-MAP-020) al intentar el update. Verificado visualmente en el sitio
  público tras invalidar caché (`POST /cache/invalidate`) — 5 slides, imágenes reales, enlaces
  internos correctos. Detalle completo en `../docs/legacy-cms-pilot-mapping.md` sección 12.
  `composer quality` ✅ (676 tests, 2 skips preexistentes no relacionados).

- **LEGACY-MAP-019 — Cerrar la lista de pendientes de la migración final (2026-08-01):**
  Pasada completa sobre los pendientes documentados en `../docs/legacy-cms-pilot-mapping.md`
  sección 10.4, a pedido explícito de David ("corrige y realiza todo... no te detengas hasta
  completarlo todo"):
  1. **Bug real de noticias**: `applyNoticias()` creaba un bloque `rich_text` requerido
     auto-creado vacío (el "Titular" de la plantilla) más uno manual redundante con el cuerpo
     real. Corregido pasando `content` por `wizard_extra` y eliminando el bloque manual; las 20
     noticias ya migradas se purgaron y re-aplicaron limpias.
  2. **Falso positivo "Desactualizado"**: mismo patrón ya diagnosticado el 2026-07-21 para
     bloques (ver `TranslationAuditSupport::collapseForBlockBadge()`), nunca corregido en la
     raíz para entradas. `EntryService::afterStore()` escribía las traducciones antes del
     housekeeping de `wizard_extra`, así que ese segundo write podía dejar `cms_entries.
     updated_at` un segundo después de `cms_entry_translations.updated_at` y disparar el falso
     "outdated". Corregido reordenando `afterStore()` (ver TASKS.md de cms-domain).
  3. **`FILE_MAX_SIZE`**: subido a 32MB sólo en `.env` local (gitignored) el tiempo necesario
     para reintentar los 3 PDFs rechazados de `sn_prensa`, y revertido a 10MB al terminar. Sin
     tocar configuración versionada ni compartida.
  4. **`sn_slider` (home hero slider)**: nuevo `applyHomeSliderSlides()` en `LegacyApplyService`
     (7° paso de Slice C) migra las 5 slides visibles de `categoria=1` (Index/home) como hijos
     `slide_banner` del `hero_slider` ya seedeado en la página `home`. Las de
     `categoria=2/3/4/5` (nosotros/historia/Upa Chalupa/Anímate) no tienen contenedor o página
     destino — eso es una decisión de diseño de página, no una transformación de datos, y
     quedó explícitamente sin tocar (ver LEGACY-MAP-018). **Seguimiento (2026-08-01):** las 5
     imágenes fallaron en el `asset-root` local (no las 2 reportadas inicialmente — error de
     conteo corregido), porque se subieron a producción después del último snapshot local. Con
     autorización de David, se descargaron desde `https://teatromuseo.cl` y se subieron al hub
     manualmente; los 5 slides ya sirven su imagen real.
  5. **`sn_contact_status`**: confirmado completamente superado por el ENUM `status` de
     `cms_form_submissions` en cms-domain — nada que migrar.
  6. **`t_expo`**: confirmado 0 filas. **`sn_personal`**: confirmado política ya documentada de
     no-migración (hashes MD5 legacy).
  7. **`sn_contact_message`** (157 filas PII) quedó explícitamente sin migrar — requiere
     decisión de David, no técnica (ver LEGACY-MAP-017).

  Verificado end-to-end: auditoría de traducciones en 100%/100%/100%/100% (0 issues), Slice
  A/B/C idempotentes tras cada cambio (segunda corrida siempre 0 creados), `composer quality`
  ✅ (676 tests, 2 skips preexistentes no relacionados) en cada paso. Detalle completo en
  `../docs/legacy-cms-pilot-mapping.md` sección 11.

- **LEGACY-MAP-016 — Ejecutar `legacy:apply --slice B/C` real + corregir abort por archivo rechazado (2026-08-01):**
  Slice B (cursos/profesores) aplicó a la primera sin bugs nuevos: 6 entradas, 7 bloques, 4
  archivos, 3 issues (`asset_missing`, campos vacíos legítimos), idempotencia confirmada.
  Slice C (exposiciones/publicaciones/noticias/festival) encontró un tercer bug real:
  `LegacyApplyService::assetFile()` no capturaba errores de `$this->hub->upload()`, así que un
  solo archivo rechazado por el hub (3 PDFs de `sn_prensa` superan `Api::$fileMaxSize` = 10MB)
  abortaba **todo el slice**, incluso después de crear decenas de entradas correctamente.
  Corregido con un `try/catch(\RuntimeException)` alrededor del upload: ahora registra un issue
  `asset_rejected` (mensaje del hub conservado literal) y continúa con el resto del slice — el
  mismo principio de "no perder la fila, no abortar el lote" que ya regía `asset_missing`. Con
  el fix: 67 entradas CMS, 35 bloques, 47 archivos, 31 issues (28 esperados + 3
  `asset_rejected`), idempotencia confirmada (0 creados en la segunda corrida). Hay ~6 archivos
  legacy más (hasta 28MB, un libro editorial) que tropezarán con el mismo límite cuando se
  migren sus tablas — **decisión de `FILE_MAX_SIZE` pendiente, no tomada unilateralmente**. Ver
  `../docs/legacy-cms-pilot-mapping.md` sección 10.3-10.4. `composer quality` ✅ (676 tests, 2
  skips preexistentes no relacionados).

- **LEGACY-MAP-015 — Ejecutar `legacy:apply --slice A` real + corregir bug de upload multipart (2026-08-01):**
  Primera ejecución material (no dry-run) contra hub/cms-domain/event-domain con JWT superadmin
  y `--asset-root` real (`teatromuseo_webapp_php/`, 2.680 assets). Falló primero en
  `LegacyHttpDomainClient::upload()`: construía el multipart en forma Guzzle
  (`[{name, contents, filename}]` con un `resource` de `fopen()` como `contents`), pero
  `CI4\HTTP\CURLRequest::applyBody()` pasa `config['multipart']` directo a
  `CURLOPT_POSTFIELDS`, que espera un array asociativo plano `field => valor` con `CURLFile`
  para el archivo — no el shape de Guzzle. Corregido: ahora construye
  `['file' => new \CURLFile(...), ...campos]`. Verificado con upload real contra el hub (`curl
  -F` directo confirmó que el endpoint en sí funcionaba antes del fix; el bug era 100% del
  lado cliente). Con el fix: Slice A aplicó 15 entradas, 7 eventos, 10 ocurrencias, 32 bloques,
  32 archivos, 7 referencias, 0 issues; una segunda corrida confirmó idempotencia exacta (0
  creados, todo reusado). Contraparte del fix en cms-domain
  (`EntryBlockTemplateInitializer` — ver su TASKS.md) necesaria para que el contenido de los
  bloques no quedara vacío. Detalle completo, incluyendo la limpieza de datos de prueba
  intermedios, en `../docs/legacy-cms-pilot-mapping.md` sección 10.2. `composer quality` ✅
  (676 tests, 2 skips preexistentes no relacionados).

- **LEGACY-MAP-014 — Mapear `director_compania` en `LegacyApplyService` (2026-08-01):**
  Contraparte del cambio de schema en `teatromuseo-cms-domain` (`compania_ficha.director` +
  `director_ref`). `LegacyApplyService::applyCmsEntry()` para `sn_compania` ahora incluye
  `'director' => director_compania` en el `block_data`. Deliberadamente NO se toca
  `director_ref` (entry_reference a `personas`) desde el ETL — buena parte de los valores
  legacy de `director_compania` son placeholder del editor (`"Director"`, `"Director El Árbol
  de Ko"`), no nombres reales; crear/emparejar automáticamente fichas de `personas` a partir de
  ese campo contaminaría la colección. Ver `../docs/legacy-cms-pilot-mapping.md` para el detalle
  completo. Verificado: `composer quality` ✅ (PHPStan 0 errores, 676 tests, 2 skips
  preexistentes no relacionados).

- **DEPS-001 — Falso positivo de "abandoned" para sebastian/code-unit (2026-07-31):** `composer
  audit` marcaba `sebastian/code-unit`/`sebastian/code-unit-reverse-lookup` (transitivos de
  PHPUnit) como abandonados. Verificado contra Packagist en vivo: **no** están abandonados — el
  flag venía de metadata obsoleta congelada en `composer.lock` desde la última vez que se
  regeneró. `composer update sebastian/code-unit sebastian/code-unit-reverse-lookup` refrescó la
  metadata sin cambiar versiones (3.0.3/4.0.1 en ambos casos) y limpió el aviso. Revisado también
  en los otros 7 repos del monorepo: ninguno tenía el flag activo (sus locks nunca capturaron el
  estado obsoleto), así que no hizo falta tocarlos.

## ✅ Completadas

- **NULL-CLEAR-001 — Fix "no se puede limpiar un campo nullable vía update" en toda la familia *UpdateRequestDTO (2026-07-30):**
  Bug arquitectónico transversal encontrado durante FILE-GUARD-001: todo Update DTO generado por el
  scaffolding usa `array_filter($v !== null)` en `toArray()` + `isset()`/`??` en `map()`, lo que
  descarta CUALQUIER campo enviado como `null` antes de llegar al Model — hacía imposible limpiar
  explícitamente un campo nullable de la BD (ej. "Quitar" un cover_file_id nunca funcionó, para
  ningún recurso). Corregido en los 4 DTOs propios del Hub que tenían columnas nullable reales:
  `PermissionUpdateRequestDTO` (description), `RoleUpdateRequestDTO` (description),
  `UserUpdateRequestDTO` (first_name/last_name/avatar_url). `ApiKeyUpdateRequestDTO` quedó sin
  tocar — ninguno de sus campos editables es nullable en `api_keys`, no había bug que arreglar ahí.
  Patrón aplicado: ternario de una sola línea por propiedad (nunca if/else — PHPStan nivel 8 marca
  `property.readOnlyAssignNotInConstructor` como falso positivo cuando hay dos sentencias de
  asignación a la misma propiedad readonly, aunque sean ramas mutuamente excluyentes) +
  `array_key_exists()` para distinguir "campo omitido" de "campo enviado como null" + acumulador
  `$mappedFields` que `toArray()` retorna directo. Columnas NOT NULL nunca aceptan null explícito
  (se tratan igual que omitidas, respetando el constraint de BD) — decisión campo por campo basada
  en el schema real (`DESCRIBE`), no un blanket fix.
  **`UserUpdateRequestDTO` requirió tocar también `UpdateUserAction::buildUpdateData()`** — ese
  action NUNCA llamaba `toArray()`, leía las propiedades del DTO directamente con sus propios
  checks `!== null` (mismo bug, duplicado). `password` quedó **deliberadamente excluido** del
  mecanismo de clearing genérico (excluido de `toArray()`/`mappedFields` por completo, igual que
  `role_ids`) — nunca debe ser posible limpiar la contraseña de un usuario a NULL vía este PATCH
  genérico; sigue el mismo trato especial de siempre (`$request->password !== null` → hash y
  escribe, si no se toca).
  Verificado end-to-end real (no solo tests): `PUT /iam/permissions/1` con body `{"description":
  null}` → 200, `SELECT` directo confirma `description IS NULL` en BD; payload que SOLO limpia un
  campo a null ya no dispara `noFieldsToUpdate` (el array ya no queda vacío tras el fix).
  `composer quality` ✅ (676/676 tests, PHPStan nivel 8 sin errores incluyendo `app/DTO`).
  **Hallazgo secundario, no corregido**: la respuesta de `PUT /iam/permissions/1` tras limpiar
  `description` la muestra como `""` en vez de `null` en el JSON — la escritura en BD es un NULL
  real (confirmado por SQL), es solo el `ResponseDTO`/mapper que castea a string vacío al serializar.
  Cosmético, no afecta integridad de datos; no se tocó (fuera de alcance de este fix).
  **Fuera de alcance, señalado pero no corregido**: el generador de scaffolding
  (`dcardenasl/ci4-api-scaffolding`, dependencia real vía Composer, no un path-repo editable en este
  workspace) sigue emitiendo el patrón `array_filter` roto en cualquier `make-crud.sh` nuevo — el fix
  real requiere una versión parcheada del paquete, no algo que se pueda arreglar editando `vendor/`.

- **FILE-GUARD-001 — Delete guard + invalidación de caché cross-domain para archivos (2026-07-30):**
  `FileService::destroy()`/`forceDestroy()` solo veían usages dentro del propio Hub
  (`file_references`), ciegos a `cover_file_id`/`gallery_file_ids` en catalog-domain/
  event-domain y a `cms_file_references` en cms-domain — un archivo en uso por un evento o
  pieza de colección se podía borrar sin aviso. Y `HubClient::invalidateFileMetaCache()`
  existía en los 3 domains pero nadie lo llamaba nunca. Ambos gaps cerrados:
  - Nuevo `App\Libraries\Domains\DomainFileUsageClient` (`DomainFileUsageClientInterface`),
    HMAC-firmado (`Config\DomainWebhooks`, env `HUB_INTERNAL_SECRET` + `{CMS,CATALOG,EVENT}_DOMAIN_URL`),
    fail-open por diseño (un domain caído no bloquea operaciones de archivo en el Hub, solo
    se loguea).
  - `FileService::collectAllUsages()` combina `fileReferenceRepository->getByFileId()` +
    `domainFileUsageClient->collectUsages()`; usado en `getUsages()` (ahora la vista "usages"
    del admin también ve cross-domain) y en el guard de `destroy()`/`forceDestroy()`.
  - `replace()`/`destroy()`/`forceDestroy()` disparan `broadcastInvalidate()` tras éxito
    (best-effort, nunca revierte la operación si un domain no responde).
  - Verificado end-to-end real (no solo tests): subida de archivo → asignado como
    `cover_file_id` de un evento real → `DELETE /files/{id}` devuelve 409 correctamente →
    `replace()` del archivo se refleja sin demora en `/api/v1/public/events/{id}` del
    event-domain (antes tardaría hasta 300s de TTL de caché) → limpieza de datos de prueba.
  - `composer quality` ✅ en los 4 repos (api + cms-domain + catalog-domain + event-domain).
  - **Hallazgo colateral, no corregido (fuera de alcance):** `EventUpdateRequestDTO::toArray()`
    y `CollectionItemUpdateRequestDTO::toArray()` usan `array_filter($v !== null)`, así que
    **ningún campo nullable puede limpiarse explícitamente vía update** (enviar
    `cover_file_id: null` se descarta en silencio en vez de hacer `NULL` en BD) — el botón
    "Quitar" del picker de imagen de portada en el admin nunca ha funcionado, para ningún
    recurso. Es un patrón preexistente en todo el DTO de update, no algo introducido aquí.

- **LEGACY-001 — Motor de migración de datos legacy (dry-run):** `app/Commands/LegacyDryRun.php`,
  `app/Libraries/LegacyMigration/` (lector de dumps SQL, resolutor de assets con guardas
  anti path-traversal, catálogo y analizador de slice A) + tablas de control
  (`2026-07-27-024000_CreateLegacyMigrationControlTables.php`) y su suite de tests unit/integration.
  Opera únicamente en modo `dry_run`; no ejecuta escrituras destructivas contra la BD legacy.
  Pendiente como próximo paso: habilitar el modo de escritura real detrás de un flag explícito
  cuando se decida ejecutar la migración productiva.

## ⚪ Backlog

- [ ] **API-012 — Docker out-of-the-box:** validar la orquestación cross-repo en `ci4-kickstart`
  después de la idempotencia de `docker/entrypoint.sh`.

## ⚠️ Señales de activación

- **API-014 — Multi-tenant nativo:** fuera de alcance mientras no exista una señal real que exija
  aislamiento físico o un SLA propio.
- **SEÑAL-API-001 — `InvalidChars` con enteros en JSON:** mantener el workaround documentado hasta
  que exista un segundo endpoint afectado o una corrección upstream de CI4.
- **FILES-001 — Endpoints de archivos faltantes:** crear tareas individuales cuando se prioricen
  `PATCH /files/{id}`, replace, regeneración de variantes o consulta de usages.

## 🏗️ Contratos de arquitectura

- **DTO-First:** toda entrada y salida de Controller usa DTOs; no arrays raw sin contrato.
- **Services puros:** no conocen HTTP ni `$request`.
- **Controllers delgados:** usar `handleRequest()` de `ApiController`.
- **Permisos:** usar separador `.`; nunca `:`.
- **Rutas:** organizar endpoints en `app/Config/Routes/v1/<dominio>.php`.
- **Tests:** todo endpoint nuevo necesita al menos un Feature test.
- **CRUD nuevo:** preferir `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
- **OpenAPI:** regenerar Swagger al cerrar cambios de endpoints.
- **Migraciones:** nunca modificar migraciones existentes; crear una nueva para cada cambio de schema.

## 🔧 Referencias

- Histórico: [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md)
- Tracker global: [`../TASKS.md`](../TASKS.md)
