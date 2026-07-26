# Consultas Avanzadas

La arquitectura desacopla la construcción de consultas de la lógica de negocio utilizando el **Patrón Repository** y un `QueryBuilder` centralizado.

## Características

### Filtrado
```bash
GET /api/v1/products?filter[price][gte]=100&filter[name][like]=%phone%
```

**Operadores:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Búsqueda
```bash
GET /api/v1/products?search=laptop
```
Busca a través de `$searchableFields` definidos en el Modelo usando FULLTEXT o LIKE.

### Ordenamiento
```bash
GET /api/v1/products?sort=-created_at,name
```
Una lista de campos separados por comas. Prefijo `-` para orden descendente. Solo se permiten campos en `$sortableFields`.

### Paginación
```bash
GET /api/v1/products?page=2&limit=50
```

## Uso en Services (Patrón Repository)

Los servicios nunca deben instanciar un `QueryBuilder` ni tocar el `Model` directamente para consultas. En su lugar, utilizan el método `paginateCriteria` proporcionado por el `RepositoryInterface`.

```php
// app/Services/Products/ProductService.php

public function index(array $criteria): array
{
    // El servicio simplemente pasa el array de criterios (filter, search, sort)
    // al repositorio. El repositorio maneja la validación y ejecución.
    return $this->productRepository->paginateCriteria(
        $criteria, 
        (int) ($criteria['page'] ?? 1), 
        (int) ($criteria['limit'] ?? 20)
    );
}
```

### Criterios Base (Avanzado)

Si necesita aplicar criterios estáticos (ej. "solo productos activos") antes de los filtros del usuario, use el callback `baseCriteria`:

```php
return $this->productRepository->paginateCriteria(
    $criteria,
    $page,
    $limit,
    function($builder) {
        $builder->where('status', 'active');
    }
);
```
