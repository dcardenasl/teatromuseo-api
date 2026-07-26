# Runbook 03 — Upgradear CodeIgniter 4 a una nueva versión minor

**Severidad:** Baja (cambio planificado) | **ETA:** 30–90 min | **Auditoría:** B11.2

## Cuándo usar

- Un nuevo minor de CI4 (`4.6.x`, `4.7.x`, ...) trajo una feature que quieres, o atiende un CVE.
- Higiene periódica de dependencias: a lo más un minor atrasado por ventanas de soporte de seguridad.

No para upgrades **major** (5.x). Esos necesitan su propio playbook con auditorías de API.

## Pre-flight

```bash
# 1. Leer el changelog upstream y la guía de migración.
#    https://github.com/codeigniter4/CodeIgniter4/blob/develop/CHANGELOG.md
#    https://codeigniter.com/user_guide/installation/upgrading.html

# 2. Notar entradas del CHANGELOG que tocan áreas que el kit usa heavy:
#    - HTTP\Filters
#    - Validation
#    - Session\Handlers
#    - Database\BaseBuilder
#    - Test\FeatureTestTrait

# 3. Verificar la superficie de pinning del kit.
grep -A1 '"codeigniter4/framework"' composer.json
```

## Procedimiento

### Paso 1 — Branch y bump

```bash
git switch -c chore/ci4-upgrade-4-6
composer require "codeigniter4/framework:^4.6" --update-with-dependencies
git diff composer.lock | head -50
```

### Paso 2 — Correr el gate localmente

```bash
composer quality   # phpstan + cs-check + tests + arch-drift
```

Roturas comunes y arreglos:

| Síntoma | Causa probable | Fix |
|---|---|---|
| Errores PHPStan en return type de `getMethod()` | CI4 cambió un type HTTP | Actualizar typehints en `app/HTTP/ApiRequest.php` |
| `composer audit` flaggea dep transitiva | CI4 bumpeó una librería vendoreada | `composer why <package>`; usualmente seguro |
| Claves de error de validación renombradas | Core de Validation cambió | `composer i18n-check` flaggeará claves faltantes; copiar del `system/Language/en/Validation.php` upstream |
| Firma de filter `before()` cambió | Raro entre minors pero posible | Actualizar cada `App\Filters\*Filter` para coincidir con la nueva interfaz |

### Paso 3 — Arreglar lo que rompe, re-correr quality

Loopear hasta que `composer quality` esté verde. **No silenciar errores PHPStan** para hacer pasar el upgrade; arreglar la causa o moverlo al baseline si es ruido conocido del framework.

### Paso 4 — Smoke test contra fresh install

```bash
# En un dir scratch:
bash <(curl -fsSL https://raw.githubusercontent.com/dcardenasl/ci4-starter-kit/main/new-project.sh)
# Confirmar que el proyecto generado bootea, migra, y sirve /health.
```

### Paso 5 — Actualizar CHANGELOG y CLAUDE.md

```markdown
## [Unreleased]

### Changed
- **CodeIgniter framework bump a ^4.6** — toma <highlight upstream>.
  Sin breaking changes a nivel de aplicación; baseline PHPStan sin cambios.
```

### Paso 6 — PR

```bash
gh pr create \
  --base dev \
  --title "chore(deps): bump codeigniter4/framework to ^4.6" \
  --body 'Ver entrada en CHANGELOG.md [Unreleased]. Probado manualmente con `new-project.sh`.'
```

## Rollback

Si surge una regresión post-merge:

```bash
git revert <merge-commit-sha>
composer install
composer quality
```

El pin del lockfile lo hace limpio. La tabla de migraciones no se afecta (los minors de CI4 no cambian el schema de `migrations`).

## Checklist post-mortem (solo en regresión)

- [ ] ¿Qué exactamente se rompió? Path de archivo + síntoma.
- [ ] ¿Lo capturó `composer quality` o solo apareció en producción?
- [ ] Si solo en producción: ¿qué test lo capturaría la próxima vez?
- [ ] ¿El fix vive en `app/` o en un workaround a nivel kit?
