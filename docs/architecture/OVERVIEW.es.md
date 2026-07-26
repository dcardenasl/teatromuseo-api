# Visión General de la Arquitectura

## Resumen Ejecutivo

Este proyecto es una **API REST de nivel empresarial** construida sobre CodeIgniter 4, siguiendo una arquitectura estrictamente en capas con clara separación de responsabilidades:

```
Controller → Service → Model → Entity
```

### Características Clave

- **Arquitectura en Capas** - Clara separación entre presentación, lógica de negocio y acceso a datos
- **Inyección de Dependencias** - Contenedor IoC para desacoplamiento y testabilidad
- **Validación Multi-Nivel** - Capa de entrada, capa de modelo, reglas de negocio
- **Autenticación JWT sin Estado** - Con tokens de refresco y soporte de revocación
- **Internacionalización Completa** - Soporte multi-idioma (en/es)
- **Sistema de Excepciones Estructurado** - Mapeo automático a códigos de estado HTTP
- **Query Builder Avanzado** - Filtrado, búsqueda FULLTEXT y paginación

---

## Capas de la Arquitectura

```
┌─────────────────────────────────────────────────────────────────────┐
│                      CAPA DE PRESENTACIÓN                           │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        FILTERS                                 │  │
│  │  CorsFilter → ThrottleFilter → JwtAuthFilter → RoleAuthFilter │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                      CONTROLLERS                               │  │
│  │   ApiController (Base) → UserController, AuthController        │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                      CAPA DE NEGOCIO                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                   VALIDACIÓN DE ENTRADA                        │  │
│  │  AuthValidation, UserValidation, FileValidation                │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       SERVICES                                 │  │
│  │  UserService, AuthService, JwtService, FileService             │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                      LIBRARIES                                 │  │
│  │  ApiResponse, QueryBuilder, StorageManager, QueueManager       │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                        CAPA DE DATOS                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        MODELS                                  │  │
│  │  UserModel (Filterable, Searchable), FileModel, AuditLogModel │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       ENTITIES                                 │  │
│  │  UserEntity, FileEntity (with computed properties)             │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                     INFRAESTRUCTURA                                 │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Database (MySQL) | Cache | Queue | Storage (Local/S3)        │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Principios SOLID

| Principio | Implementación |
|-----------|----------------|
| **Responsabilidad Única** | Cada clase tiene una sola razón para cambiar |
| **Abierto/Cerrado** | Extensible mediante herencia y traits, cerrado para modificación |
| **Sustitución de Liskov** | Los servicios implementan interfaces intercambiables |
| **Segregación de Interfaces** | Interfaces específicas por dominio |
| **Inversión de Dependencias** | Depender de abstracciones (interfaces) no de implementaciones |

---

## Responsabilidades de las Capas

### Capa de Presentación
**Controllers & Filters**

- Manejar peticiones y respuestas HTTP
- Aplicar middleware (autenticación, throttling, CORS)
- Recopilar y sanitizar entrada
- Delegar a servicios
- Retornar respuestas JSON formateadas

**Reglas:**
- NO lógica de negocio en controllers
- SOLO código relacionado con HTTP
- Controladores delgados, servicios robustos

### Capa de Negocio
**Services & Libraries**

- Contener toda la lógica de negocio
- Orquestar operaciones entre modelos
- Validar reglas de negocio
- Transformar datos
- Lanzar excepciones de dominio
- Formatear respuestas API

**Reglas:**
- Los servicios retornan arrays (via ApiResponse)
- Los servicios lanzan excepciones (nunca retornan arrays de error)
- Los servicios implementan interfaces para testabilidad

### Capa de Datos
**Models & Entities**

- **Models**: Operaciones de base de datos mediante query builder
- **Entities**: Representación y transformación de datos

**Reglas:**
- NO lógica de negocio en modelos
- NO SQL crudo (usar query builder)
- Los modelos retornan entities
- Las entities ocultan campos sensibles

### Capa de Infraestructura
**Database, Cache, Queue, Storage**

- Sistemas externos y drivers
- Configurados mediante variables de entorno
- Implementaciones intercambiables (S3 vs almacenamiento local)

---

## Ciclo Petición/Respuesta

Cada petición API sigue este camino:

```
1. HTTP Request
   ↓
2. Routing (coincide controller + método)
   ↓
3. Filters (CORS → Throttle → JwtAuth → RoleAuth)
   ↓
4. Controller (recopilar datos, sanitizar, delegar)
   ↓
5. Service (validar, procesar, formatear respuesta)
   ↓
6. Model (consultar base de datos)
   ↓
7. Entity (castear tipos, ocultar campos)
   ↓
8. Service (formatear con ApiResponse)
   ↓
9. Controller (establecer estado HTTP, retornar JSON)
   ↓
10. HTTP Response
```

**Tiempos típicos (petición típica):**
- Filters: ~5ms
- Controller: ~2ms
- Service: ~10-50ms (lógica de negocio)
- Model: ~5-20ms (base de datos)
- Total: ~25-80ms

---

## Estructura de Directorios

```
app/
├── Config/
│   ├── Routes.php              # Definiciones de rutas
│   ├── Services.php            # Contenedor IoC
│   └── Filters.php             # Alias de filtros
│
├── Controllers/
│   ├── ApiController.php       # Controlador base
│   └── Api/V1/                 # Controllers API v1
│
├── Services/                   # Lógica de negocio
├── Interfaces/                 # Contratos de servicios
├── Models/                     # Acceso a base de datos
├── Entities/                   # Objetos de datos
├── Filters/                    # Middleware
├── Exceptions/                 # Excepciones personalizadas
├── Libraries/                  # Utilidades compartidas
├── Validations/                # Validación de entrada
├── Traits/                     # Comportamientos reutilizables
└── Language/                   # Traducciones i18n
```

---

## Decisiones de Diseño

### ¿Por qué Arquitectura en Capas?
✅ **Mantenibilidad** - Límites claros, fácil localizar código
✅ **Testabilidad** - Cada capa se prueba independientemente
✅ **Escalabilidad** - Las capas pueden optimizarse o reemplazarse
✅ **Colaboración en Equipo** - Diferentes desarrolladores trabajan en diferentes capas

### ¿Por qué Services + Interfaces?
✅ **Inyección de Dependencias** - Fácil intercambiar implementaciones
✅ **Testabilidad** - Mock de servicios en pruebas
✅ **Reusabilidad** - Los servicios pueden usarse por múltiples controllers

### ¿Por qué Entities?
✅ **Type Safety** - Casteo automático de tipos
✅ **Seguridad** - Ocultar campos sensibles automáticamente
✅ **Encapsulación** - Propiedades computadas y lógica de dominio

### ¿Por qué Excepciones Personalizadas?
✅ **Consistencia** - Mapeo automático a códigos de estado HTTP
✅ **Claridad** - La intención es clara por el tipo de excepción
✅ **Manejo Centralizado** - Un solo lugar para formatear respuestas de error

---

## Próximos Pasos

**Entender las capas:**
- Lee [LAYERS.es.md](LAYERS.es.md) para explicación detallada de cada capa

**Verlo en acción:**
- Lee [REQUEST_FLOW.es.md](REQUEST_FLOW.es.md) para recorrido completo de una petición

**Aprender sistemas específicos:**
- [FILTERS.es.md](FILTERS.es.md) - Sistema de middleware
- [VALIDATION.es.md](VALIDATION.es.md) - Validación multi-nivel
- [EXCEPTIONS.es.md](EXCEPTIONS.es.md) - Manejo de excepciones
- [AUTHENTICATION.es.md](AUTHENTICATION.es.md) - Flujo de autenticación JWT

**Hoja de ruta completa:**
- Ver [README.es.md](README.es.md) para rutas de aprendizaje
