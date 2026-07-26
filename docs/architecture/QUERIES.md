# Advanced Querying

The architecture decouples query construction from business logic using the **Repository Pattern** and a centralized `QueryBuilder`.

## Features

### Filtering
```bash
GET /api/v1/products?filter[price][gte]=100&filter[name][like]=%phone%
```

**Operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Searching
```bash
GET /api/v1/products?search=laptop
```
Searches across `$searchableFields` defined in the Model using FULLTEXT or LIKE.

### Sorting
```bash
GET /api/v1/products?sort=-created_at,name
```
A comma-separated list of fields. Prefix `-` for descending. Only fields in `$sortableFields` are allowed.

### Pagination
```bash
GET /api/v1/products?page=2&limit=50
```

## Usage in Services (Repository Pattern)

Services should never instantiate a `QueryBuilder` or touch the `Model` directly for queries. Instead, they use the `paginateCriteria` method provided by the `RepositoryInterface`.

```php
// app/Services/Products/ProductService.php

public function index(array $criteria): array
{
    // The service simply passes the raw criteria array (filter, search, sort)
    // to the repository. The repository handles validation and execution.
    return $this->productRepository->paginateCriteria(
        $criteria, 
        (int) ($criteria['page'] ?? 1), 
        (int) ($criteria['limit'] ?? 20)
    );
}
```

### Base Criteria (Advanced)

If you need to apply static criteria (e.g., "only active products") before the user's filters, use the `baseCriteria` callback:

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

