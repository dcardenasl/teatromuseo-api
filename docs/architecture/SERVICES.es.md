# Capa de Servicio e Inversión de Control (IoC)

La capa de Servicio contiene toda la lógica de negocio y orquesta las operaciones del dominio. En esta arquitectura, los servicios están **organizados por dominios**, siguen un **patrón de composición** y están desacoplados de la persistencia mediante **Repositorios**.

---

## Organización Orientada a Dominios

Los servicios se agrupan por dominio funcional para garantizar una alta cohesión y evitar las "Clases Todopoderosas".

- `app/Services/Auth/`: Login, Registro, OAuth.
- `app/Services/Tokens/`: JWT, Tokens de Refresco, Revocación.
- `app/Services/Users/`: Gestión de Identidad y RBAC.
- `app/Services/Files/`: Orquestación de almacenamiento y procesamiento de archivos.
- `app/Services/System/`: Infraestructura (Auditoría, Email, Métricas).

---

## Patrón de Composición de Servicios

Los servicios grandes se descomponen en componentes especializados inyectados a través del constructor. Esto mantiene a los orquestadores ligeros y a la lógica testeable.

### Componentes de Soporte (Support):
- **Actions**: Encapsulan flujos de comando con efectos de escritura (ej. `RegisterUserAction`).
- **Handlers**: Encapsulan lógica de múltiples pasos (ej. `GoogleAuthHandler`).
- **Guards**: Centralizan aserciones de seguridad (ej. `UserRoleGuard`).
- **Mappers**: Manejan transformaciones de entidades a DTOs (ej. `AuthUserMapper`).

---

## Patrón Repository

Para asegurar que los servicios sean completamente agnósticos del framework de base de datos, nunca tocan `CodeIgniter\Model` directamente. En su lugar, inyectan un **RepositoryInterface**.

- **Repositories:** Responsables de toda la persistencia y recuperación de datos.
- **Interfaces:** Los servicios dependen de interfaces (ej. `UserRepositoryInterface`) en lugar de implementaciones concretas.
- **Testabilidad:** Los repositorios se pueden mockear fácilmente para pruebas unitarias puras de los servicios.

---

## Servicios Puros e Inmutables

Todos los nuevos servicios deben ser **Puros**, **Sin Estado** (Stateless) y declarados como `readonly class` (PHP 8.2+).

- **Entrada:** DTOs específicos, SecurityContext o tipos escalares.
- **Salida:** DTOs, Entidades o `OperationResult`.
- **Errores:** Lanzados como Excepciones personalizadas que implementan `HasStatusCode`.
- **Inmutabilidad:** Las dependencias se inyectan a través del constructor y nunca cambian.

### Ejemplo (Composición y Repositorio)

```php
// app/Services/Auth/AuthService.php
readonly class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthUserMapper $userMapper,
        protected UserAccountGuard $userAccessPolicy
    ) {}

    public function login(LoginRequestDTO $request): LoginResponseDTO
    {
        // 1. Recuperación mediante Repositorio
        $user = $this->userRepository->findByEmail($request->email);
        
        // 2. Aserción de negocio mediante Guard
        $this->userAccessPolicy->assertCanAuthenticate($user);
        
        // 3. Transformación mediante Mapper
        return $this->userMapper->mapToResponse($user);
    }
}
```

---

## Registro de Servicios (IoC)

Todos los servicios y sus dependencias deben registrarse en `app/Config/Services.php` (o en un trait de proveedor de servicios específico del dominio).

```php
// app/Config/Services.php
public static function authService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('authService');
    }
    
    return new \App\Services\Auth\AuthService(
        static::userRepository(), // Inyectar Repositorio en lugar de Modelo
        new \App\Services\Auth\Support\AuthUserMapper(),
        new \App\Services\Users\UserAccountGuard()
    );
}
```

---

## Beneficios

1. **Aislamiento:** La lógica de negocio es 100% independiente de Active Record de CodeIgniter.
2. **Mockability:** Testea orquestadores sin base de datos mockeando la interfaz del repositorio.
3. **Descubribilidad:** Carpetas de dominio claras e interfaces explícitas.
