# CRUD Playbook (Arquitectura Millonaria)

## 1) Preparación

- Inspeccionar ejemplos reales (DTO-First):
  - `app/Controllers/Api/V1/Users/UserController.php`
  - `app/DTO/Request/Users/UserIndexRequestDTO.php`
  - `app/DTO/Response/Users/UserResponseDTO.php`
  - `app/Services/Users/UserService.php`
- Confirmar filtros/rutas en `app/Config/Routes.php`.

## 2) Orden recomendado de implementación

1. **Bootstrap:** `bash bin/make-crud.sh ...` (o `php spark make:crud ...` interactivo) + `php spark module:check ...`.
2. **Base de Datos:** crear migration(s) (no las genera el scaffold), luego ajustar Entity y Model.
3. **Contrato (DTOs):** Request DTO (entrada) y Response DTO (salida con OpenAPI attributes).
4. **Lógica:** Service Interface, Service Implementation (Pure Service) + estrategia de repositorio.
5. **Infraestructura:** Services config, Language files.
6. **Transporte:** Controller (extends `ApiController`), Routes.
7. **Documentación:** `php spark swagger:generate`.
8. **Calidad:** Tests (Unit, Feature) y `composer quality`.

## 3) Capa DTO (Mandatoria)

- **Request DTO:** Clase `readonly` de PHP 8.2. Validar en constructor vía `BaseRequestDTO::rules()` + `BaseRequestDTO::validate()`.
- **Response DTO:** Clase `readonly` de PHP 8.2. Incluir atributos `#[OA\Property]`. Mapear desde array/entity vía método estático `fromArray()`.

## 4) Servicio Puro

- NO usar `ApiResponse` ni códigos HTTP.
- Recibir DTOs específicos.
- Retornar DTOs o Entidades.
- Usar `GenericRepository` por defecto; escalar a repositorio dedicado solo cuando las queries de dominio lo requieran.
- Lanzar excepciones personalizadas para errores.

## 5) Controlador Moderno

- Extender `ApiController`.
- Resolver el servicio principal en `resolveDefaultService()`.
- Usar `handleRequest('method', RequestDTO::class)` o closure cuando debas fijar parámetros de ruta.
- La normalización de salida (`ApiResult` + JSON) es automática.

## 6) Definición de Terminado (Done)

- [ ] DTOs inmutables creados y validados.
- [ ] Servicio desacoplado de la capa API.
- [ ] Atributos OpenAPI integrados en el Response DTO.
- [ ] `php spark swagger:generate` sin errores.
- [ ] Tests unitarios validando retornos de DTO.
- [ ] Tests de feature validando estructura JSON final.
- [ ] `composer quality` en verde (PHPStan, CS-Fixer).
