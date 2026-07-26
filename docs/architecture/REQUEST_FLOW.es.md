# Flujo de Solicitud

Este documento describe el recorrido de una solicitud HTTP completa desde el inicio hasta el final.

---

## Diagrama de Flujo Completo

```
Solicitud HTTP
     │
     ▼
┌─────────────────────────────────────────┐
│ 1. ENRUTAMIENTO                         │
│    - Mapear URL al controlador/método   │
│    - Asignar filtros                    │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 2. FILTROS (Middleware)                 │
│    Throttle → JwtAuth → RoleAuth        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLADOR (Orquestación)           │
│    - collectRequestData() mezcla inputs │
│    - establishSecurityContext()         │
│    - handleRequest() ejecuta destino    │
└─────────────────────────────────────────┘
     │
     ▼

> **⚠️ ADVERTENCIA CRÍTICA:** `collectRequestData()` tiene prohibido llamar a `$request->getBody()` si la petición es `multipart/form-data`. Leer el cuerpo crudo consume el stream `php://input` y PHP perderá la capacidad de procesar los archivos en `$_FILES`, rompiendo el flujo de subida.
┌─────────────────────────────────────────┐
│ 4. CAPA DTO (El Escudo)                 │
│    - Autovalidación en constructor      │
│    - Recibe payload enriquecido         │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. SERVICIO DE DOMINIO (Lógica)         │
│    - Descompuesto en Handlers/Guards    │
│    - Lógica de dominio pura             │
│    - Devuelve DTO u OperationResult     │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. PIPELINE DE RESPUESTA                │
│    - ApiResponse::fromResult()          │
│    - Normalización en ApiResult         │
│    - Controlador renderiza JSON         │
└─────────────────────────────────────────┘
     │
     ▼
Respuesta HTTP (JSON)
```

---

## Ejemplo: Crear Usuario (POST /api/v1/users)

### 1. Controlador y DTO

```php
// ApiController::handleRequest
$data = $this->collectRequestData();
$context = $this->establishSecurityContext();

// BaseRequestDTO::__construct
$this->validate($data);
$this->map($data);
```

### 2. Lógica de Servicio (Compuesta)

```php
public function store(UserStoreRequestDTO $request): UserResponseDTO
{
    // Delegar seguridad
    $this->roleGuard->assertCanAssignRole(...);

    $userId = $this->model->insert($request->toArray());
    
    // Delegar procesos secundarios
    $this->invitationService->sendInvitation($user);

    return $this->mapToResponse($user);
}
```

### 3. Normalización Automática

1.  `ApiResponse::fromResult()` recibe `UserResponseDTO`.
2.  Convierte recursivamente a array.
3.  Envuelve en `ApiResult` con estado `201`.
4.  `ApiController` renderiza el JSON final.

---

## Flujo de Error

Si ocurre una excepción:
1.  `ApiController::handleException()` la captura.
2.  `ExceptionFormatter::format()` determina el entorno y seguridad.
3.  Devuelve un `ApiResult` con el estado y la carga útil de error.
4.  El controlador renderiza el JSON.

---

## Conclusiones Clave

1. **Flujo Lineal** - Transición ordenada a través de las capas.
2. **Composición** - Los servicios delegan tareas especializadas.
3. **Falla Rápido** - Los DTOs detienen datos inválidos antes de la lógica.
4. **Respuestas Consistentes** - `ApiResult` garantiza un formato universal.
5. **Conciencia Contextual** - El `SecurityContext` se inyecta en el borde HTTP antes de crear DTOs.
