# TASKS — ci4-website-builder-api

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento cross-repo: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

*(vacío)*

## ✅ Completadas

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

  **Pendiente, fuera de alcance de este fix:** `events.gallery_file_ids` tiene el mismo problema
  (0/381, la vista de galería del evento en el sitio público también queda vacía) — requiere
  threading del listado de `file_id`s ya resueltos por `applyGallery()` hacia `applyEvent()` en
  formato CSV, más su propia reconciliación. No implementado; pendiente de decisión.

  10 tests nuevos/extendidos en `LegacyApplyServiceTest.php` (reconciliación de entrada CMS +
  reconciliación de evento en el mismo test de 3 corridas), `composer quality` ✅ (692 tests, 2
  skips preexistentes).

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
