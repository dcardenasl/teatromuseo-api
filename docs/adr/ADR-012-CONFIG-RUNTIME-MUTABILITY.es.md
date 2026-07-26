# ADR-012: Los valores de Config se resuelven al boot, no en runtime

## Estado
Aceptado (auditoría B11.1, 2026-05-07)

## Contexto

`Config\Api` (y clases de config similares) leen variables de entorno en su constructor:

```php
$this->jwtAccessTokenTtl = (int) $this->envValue('JWT_ACCESS_TOKEN_TTL', 3600);

private function envValue(string $key, $default = null)
{
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return env($key, $default);
}
```

Dos patrones a destacar:

1. El uso de `getenv()` primero, `env()` después.
2. Las propiedades se pueblan al momento de construcción y se mantienen como `public` planas (no `readonly`).

La auditoría de mayo 2026 (hallazgo F32) marcó esto como "mutabilidad de config en runtime via `getenv()`" — `getenv()` lee `$_SERVER`/`$_ENV` que pueden mutarse a mitad de request via `putenv()`. La auditoría preguntó si esa mutabilidad era intencional.

Este ADR resuelve la pregunta.

## Decisión

**Los valores de Config se resuelven al boot y se tratan como inmutables durante la vida de la request.**

- El patrón del constructor (leer env una vez, guardar en propiedad) es el contrato. Cualquiera que lea `config('Api')->jwtAccessTokenTtl` en cualquier punto posterior de la request obtiene el valor tal como estaba cuando el singleton de Config fue instanciado por primera vez.
- `getenv()` vs `env()` es un detalle de implementación menor del orden de bootstrap de CI4: `getenv()` funciona en fases muy tempranas cuando `Services::dotenv()` de CI4 aún no ha corrido (CLI commands pre-boot, e.g. scripts cargados por `spark`). `env()` es el mismo valor en estado estable. El par-con-fallback existe para que la misma clase Config funcione en ambos contextos.
- La declaración `public` (no `readonly`) es convención CI4 para que las clases Config se puedan `injectMock`-ear en tests; no es invitación a mutar valores desde código de aplicación.

**El código de aplicación NO DEBE llamar a `putenv()` para cambiar config en runtime.** Hacerlo no afecta a singletons ya instanciados y produce bugs sutiles donde un path ve el valor viejo y otro ve el nuevo.

**Los operadores DEBEN tratar cambios a `.env` (o las directivas `Environment=` de systemd, secciones `env:` de k8s, etc.) como un evento de deploy.** Un reload (`systemctl reload php-fpm`, restart de pod) es requerido para tomarlos.

## Consecuencias

### Positivas

- Razonar sobre config es local — leer el constructor, sabes la forma. Sin acción-a-distancia espeluznante.
- Los tests pueden override config limpiamente via `Factories::injectMock('config', 'Api', $instance)` (ver `DeprecationHeadersFilterTest` como ejemplo) sin preocuparse de que `putenv()` les compita.
- Consumidores con caché que mantienen referencia al objeto Config (e.g. resolvers cacheados por 60 segundos) no arriesgan drift de config a mitad de caché.

### Negativas

- Los workflows de "hot-reload config" (cambiar TTLs sin restart de proceso) no se soportan. Operadores que quieran esto deben agregar su propia capa runtime-mutable (e.g. un repositorio `RuntimeConfig` respaldado por DB) encima de la config estática — no flaggearla on via truco de env.
- El `putenv()` en tests debe acompañarse de un reset del singleton de Config, con flush via `Factories::reset('config')` o `Services::resetSingle()`.

### Neutras

- Algunas propiedades de Config siguen siendo no-`readonly` para preservar la ergonomía de `injectMock` de CI4. Código de aplicación que las muta está haciendo algo mal; tratarlo como code-review smell.

## Punteros

- `app/Config/Api.php` — el patrón del constructor que este ADR documenta.
- `tests/Unit/Filters/DeprecationHeadersFilterTest.php` — ejemplo de override correcto en tests via `Factories::injectMock`.
- Hallazgo de auditoría F32 (mayo 2026) — el detonante de este ADR.
