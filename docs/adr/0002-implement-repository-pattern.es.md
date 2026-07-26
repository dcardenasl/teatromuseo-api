# 2. Implementar Patrón Repository para Persistencia de Datos

Fecha: 2026-03-03

## Estado

Aprobado

## Contexto

Inicialmente, `BaseCrudService` y los servicios de dominio dependían directamente de `CodeIgniter\Model` y `QueryBuilder`. Aunque funcional, esto acoplaba fuertemente la lógica de negocio a Active Record de CodeIgniter. Esto dificultaba los unit tests puros sin base de datos y limitaba el reemplazo del mecanismo de almacenamiento (por ejemplo, API externa o Doctrine) sin reescribir la capa de servicios.

## Decisión

Se desacopló la capa de servicios de los modelos mediante el **patrón Repository**:
1. **RepositoryInterface:** todas las operaciones de persistencia deben adherir a `App\Interfaces\Core\RepositoryInterface`.
2. **BaseRepository:** una implementación genérica (`App\Repositories\GenericRepository`) envuelve `CodeIgniter\Model` e internaliza lógica de `QueryBuilder` (filtros, búsqueda y orden).
3. **Capa de servicios:** ahora inyecta `RepositoryInterface` por constructor DI y pasa criterios genéricos en arrays, sin manipular directamente el query builder.

## Consecuencias

- **Positivas:** aislamiento completo de la capa de acceso a datos. Los servicios son 100% agnósticos al framework de base de datos. Los unit tests se pueden resolver con mocks in-memory simples.
- **Negativas:** agrega una capa de indirección. Los desarrolladores deben exponer queries específicas a través de interfaces de repositorio, en lugar de llamar `$this->model->where(...)` dentro del servicio.
