# TASKS — ci4-website-builder-api

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

- [ ] **LEGACY-MAP-022 — Preparación para la migración completa: asset-root + limpieza de datos.**
  El `asset-root` local (`teatromuseo_webapp_php`) está incompleto para contenido subido
  recientemente (ya golpeó esto 2 veces: slider del home, portada de Anímate). A escala completa
  (miles de imágenes) esto generará muchos `asset_missing`. Antes de migrar en serio: (1) escribir
  un script que recorra los paths de imagen de cada tabla objetivo, detecte cuáles faltan en el
  snapshot local, y las descargue de `https://teatromuseo.cl` (requiere autorización explícita de
  David — son potencialmente cientos de archivos). (2) Confirmar con David exclusión de basura ya
  detectada: 2 filas en `sn_obra` con `titulo_obra` "Test"/"TEst". No se decide unilateralmente.

- [ ] **LEGACY-MAP-023 — Migración completa Slice B: cursos y profesores.** Quitar los límites
  hardcodeados en `LegacyApplyService::applyCourses()` (`array_slice($courses, 0, 3)` y
  `count($teachers) >= 20`) — hoy solo 3 de 53 `sn_escuela` y 3 de 57 `sn_profesor` están
  migrados. Directamente relevante: es lo que ahora representa "Cursos" para la decisión de
  TeatroEscuela (LEGACY-MAP-018). Candidata a ir primero — tamaño manejable, sin bloqueos de
  diseño pendientes.

- [ ] **LEGACY-MAP-024 — Migración completa Slice A: obras, compañías, galería y videos.** Quitar
  `array_slice($workRows, 0, 10)`, el cap `count($referencedCompanyIds) < 3`, y
  `array_slice($videoGroups, 0, 5, true)` en `applyWorks()`. Hoy: 11 de 759 `sn_obra`, 3 de 235
  `sn_compania`, 5 de 53 `sn_youtube`. La más grande — probablemente conviene correrla en varias
  pasadas controladas en vez de una sola corrida masiva, dado el volumen de llamadas HTTP y de
  imágenes (`sn_slider_cartelera`, 1320 filas, escala junto con esta).

- [ ] **LEGACY-MAP-025 — Migración completa Slice C: noticias y publicaciones.** Quitar
  `array_slice($newsRows, 0, 20)` en `applyNoticias()` (20 de 80 hoy) y subir/quitar
  `$pubLimit = 30` en `applyPublicaciones()` (30 de 66 hoy, combinando `sn_editorial` +
  `sn_prensa` + `sn_administracion`). `sn_expo`/`sn_funcionarios`/`sn_museo`/`sn_upa` ya están
  completos, no requieren cambios.

- [ ] **LEGACY-MAP-026 — Decisión de diseño: sliders de páginas no-home (`sn_slider`
  categorías 2-5).** De 499 filas de `sn_slider`, solo las 5 de categoría 1 (home) están
  migradas — las de "Quienes Somos"/"Historia"/"Upa Chalupa"/"Anímate" (categorías 2-5) no tienen
  página/contenedor destino en la IA actual del sitio nuevo (mismo bloqueo ya documentado en
  LEGACY-MAP-018 sección 11.6 del pilot mapping). Requiere decisión de David antes de ser
  trabajo técnico.

## ✅ Completadas

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
