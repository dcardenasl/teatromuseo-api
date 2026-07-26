# Sistema de Validación

> ℹ️ La versión autoritativa de este documento es la inglesa: [`VALIDATION.md`](./VALIDATION.md). Si esta traducción contradice a la inglesa, asume que la inglesa es la correcta.

Este proyecto valida la entrada en la frontera, dentro de los **Request DTOs** —
_no_ en servicios, controllers ni middleware. Cuando un servicio recibe un DTO,
los datos que contiene ya cumplen las reglas declaradas en el DTO.

## Capas de Validación

1. **Validación DTO:** Los Request DTOs (`app/DTO/Request/`) extienden
   `dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO` y declaran un array `rules()`. El
   constructor de la base ejecuta `ValidationInterface` de CI4 contra `$data` antes
   de llamar a `map()`; si alguna regla falla, lanza `ValidationException` (HTTP 422).
   Una vez existe la instancia, los datos son válidos.
2. **Validación de Modelo:** `Model::$validationRules` en modelos auditables es una
   segunda línea de defensa contra escrituras directas al repositorio (seeders,
   migraciones, tooling interno). No reemplaza a la validación DTO sobre input de
   usuarios.
3. **Reglas de Negocio:** Lógica pura del servicio — unicidad, precondiciones de
   autorización, chequeos de estado de workflow — vive en servicios y lanza excepciones
   de dominio (`AuthorizationException`, `ConflictException`, `BadRequestException`,
   etc.) que `ApiController::handleRequest()` traduce al HTTP status correcto.

---

## Auto-Validación de DTOs

`BaseRequestDTO::__construct(array $data, ?ValidationInterface $validation = null)`
ejecuta `validate($data)` (que corre `rules()`) y luego `map($data)`. El servicio de
validación lo inyecta automáticamente
`dcardenasl\Ci4ApiCore\Support\RequestDtoFactory`, que es lo que
`ApiController::handleRequest()` usa internamente.

```php
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LoginRequest', required: ['email', 'password'])]
readonly class LoginRequestDTO extends BaseRequestDTO
{
    public string $email;
    public string $password;

    public function rules(): array
    {
        return [
            'email'    => 'required|valid_email|max_length[255]',
            'password' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->email    = strtolower(trim((string) $data['email']));
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return ['email' => $this->email, 'password' => $this->password];
    }
}
```

### Beneficios
- **Hacer imposibles los estados ilegales** — los servicios reciben un objeto tipado y
  validado, no un array asociativo.
- **Fail fast** — la petición se rechaza antes de que corra cualquier servicio.
- **Único lugar para las reglas** — `rules()` es la fuente canónica de qué acepta un
  payload.
- **Consistencia entre HTTP, CLI y tests** — la misma clase DTO aplica las mismas
  reglas sin importar el origen de la llamada.

### Mensajes personalizados
Sobrescribe `messages()` para devolver mensajes por regla al estilo CI4 (`field.rule`).
Mantén los mensajes como claves `lang()` para que se traduzcan (ver el test de
arquitectura `tests/Unit/Architecture/ValidationI18nConventionsTest`, que falla si
encuentra strings hardcoded dentro del array de errores de `ValidationException`).

```php
public function messages(): array
{
    return [
        'email' => [
            'required'    => lang('Validation.emailRequired'),
            'valid_email' => lang('Validation.emailInvalid'),
        ],
    ];
}
```

---

## Lanzar errores de validación manualmente

Algunos DTOs necesitan reglas cruzadas o normalización que las reglas de CI4 no
expresan. Hacé el check dentro de `map()` y lanzá `ValidationException` con un array
de errores cuyos valores sean llamadas a `lang()`:

```php
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

protected function map(array $data): void
{
    $rawIds = $data['ids'] ?? null;
    if (!is_array($rawIds) || $rawIds === []) {
        throw new ValidationException(
            lang('Files.bulk_ids_required'),
            ['ids' => lang('Files.bulk_ids_required')]
        );
    }
    // …
}
```

El test de arquitectura rompe el build si alguna `ValidationException` se lanza con un
mensaje hardcoded dentro de su array de errores.

---

## Reglas Comunes en Uso

Estas reglas nativas de CI4 cubren la mayoría de los casos:

- `required`, `permit_empty`
- `string`, `integer`, `is_natural`, `is_natural_no_zero`
- `valid_email`, `valid_email_idn`
- `min_length[N]`, `max_length[N]`
- `less_than[N]`, `greater_than[N]`
- `in_list[a,b,c]`
- `matches[other_field]`

Reglas personalizadas registradas en `app/Config/Validation.php`:

- `strong_password` — aplica la política de password del proyecto.

Corré la suite (`vendor/bin/phpunit tests/Unit/Architecture/`) para verificar que
todos los DTOs y convenciones de validación se respetan.

---

## Lo que este proyecto **no** usa

- **No hay `InputValidationService`** — ese patrón fue eliminado en v2.0.0. Si lo
  encontrás en docs viejos o comentarios, es una referencia obsoleta.
- **No hay helper global `validateOrFail()`** — la validación corre dentro de
  `BaseRequestDTO`, no por un helper procedural.
- **No hay validación en la capa de servicio** — los servicios confían en sus DTOs de
  entrada. Si un servicio chequea una regla de negocio (p. ej. unicidad contra la BD),
  lanza una excepción de dominio, no una `ValidationException`.
