# Las Capas de Arquitectura

Este documento explica cada capa en detalle: Controller, DTO, Service, Repository, Model y Entity.

---

## Capa Controller

**Ubicación:** `app/Controllers/Api/V1/`

**Responsabilidad:** Actuar como un orquestador ligero entre la petición HTTP y la Capa de Servicio.

### ApiController (Clase Base)

Todos los controllers API extienden `ApiController.php`. Este provee un método declarativo `handleRequest` que automatiza:
1. **RequestDataCollector:** Centraliza la combinación de datos de todas las fuentes (GET, POST, JSON, Archivos) usando un servicio compartido.
2. **RequestDtoFactory:** Instancia la clase DTO solicitada, inyectando una `ValidationInterface` compartida para asegurar reglas consistentes sin llamadas estáticas.
3. Manejo de excepciones y transformación a JSON mediante formateadores especializados.
4. Normalización de respuesta (envoltura de éxito/error y clave `data`).

### Patrón: Orquestación Declarativa

```php
public function create(): ResponseInterface
{
    // El controlador declara QUÉ ejecutar y QUÉ DTO valida la entrada.
    return $this->handleRequest('store', UserStoreRequestDTO::class);
}
```

---

## Capa DTO (Data Transfer Objects)

**Ubicación:** `app/DTO/`

**Responsabilidad:** Garantizar la integridad de los datos, seguridad de tipos y estabilidad del contrato.

### Request DTOs (Guardianes de Entrada)
- **Clase Base:** Extienden de `BaseRequestDTO`.
- **Inyección en Constructor:** Reciben una instancia de `ValidationInterface` desde el `RequestDtoFactory`.
- **Auto-validación:** La validación ocurre en el constructor mediante el método `rules()`. 
- **Seguridad:** Si un objeto DTO existe en memoria, se garantiza que los datos son válidos.
- **Inmutabilidad:** Clases `readonly` de PHP 8.2.

---

## Capa Service

**Ubicación:** `app/Services/`

**Responsabilidad:** Lógica de negocio, integridad transaccional y reglas de dominio.

### Servicios Puros y Sin Estado

Los servicios deben ser agnósticos de HTTP, JSON o modelos de base de datos directos. Utilizan **Repositorios** para la persistencia.

```php
readonly class ProductService implements ProductServiceInterface
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function store(ProductRequestDTO $request): ProductResponseDTO
    {
        // 1. Lógica
        // 2. Persistencia mediante Repositorio
        $product = $this->productRepository->insert($request->toArray());
        // 3. Retorno de DTO
    }
}
```

---

## Capa Repository

**Ubicación:** `app/Repositories/`

**Responsabilidad:** Persistencia de datos, construcción de consultas y abstracción de la base de datos.

- **Aislamiento:** Los repositorios envuelven los Modelos de CodeIgniter y manejan toda la lógica del `QueryBuilder`.
- **Criterios:** Los servicios pasan arrays de criterios genéricos a los repositorios para consultas complejas.
- **Sin Estado:** Los repositorios aseguran que los query builders se reinicien entre llamadas para evitar fugas de estado.

---

## Capas Model y Entity

**Ubicación:** `app/Models/` y `app/Entities/`

**Responsabilidad:** Representación de datos.

- **Models:** Heredan del Modelo de CodeIgniter. Son consumidos ÚNICAMENTE por los Repositorios.
- **Auditable:** Los modelos usan el trait `Auditable`, que recibe una `AuditServiceInterface` inyectada mediante el Contenedor de Servicios.
- **Entities:** Representan los datos de una fila. `UserEntity` sanitiza explícitamente los campos sensibles en su método `toArray()`.

---

## Resumen

| Capa | Responsabilidad | Patrón |
|-------|----------------|---------|
| **Controller** | Orquestación | `RequestDataCollector` + `RequestDtoFactory` |
| **DTO** | Integridad | Clase `readonly` validada |
| **Service** | Lógica | Puro, Sin Estado, `readonly` |
| **Repository** | Abstracción | Desacopla el Servicio del Modelo |
| **Model** | Persistencia | Query Builder Auditable |
| **Entity** | Representación | Fila fuertemente tipada con sanitización |

**Flujo:**
```
Petición → Controller (Orquestador) → RequestDTO (Guardián) → Service (Lógica) → Repository (Almacenamiento) → Model → Entity → ResponseDTO (Contrato) → JSON
```
