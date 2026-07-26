# CRUD Snippets (Arquitectura Modernizada)

## Controller (Declarativo)

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Catalog;

use App\Controllers\ApiController;
use App\DTO\Request\Catalog\ProductIndexRequestDTO;
use App\DTO\Request\Catalog\ProductCreateRequestDTO;
use App\Interfaces\Catalog\ProductServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ProductController extends ApiController
{
    protected ProductServiceInterface $productService;

    protected function resolveDefaultService(): object
    {
        $this->productService = Services::productService();

        return $this->productService;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ProductIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', ProductCreateRequestDTO::class);
    }
}
```

## Interface (Tipada)

```php
<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use App\Interfaces\Core\CrudServiceContract;

interface ProductServiceInterface extends CrudServiceContract
{
    // Métodos específicos de dominio opcionales.
}
```

## Request DTO (Autovalidado)

```php
<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class ProductCreateRequestDTO extends BaseRequestDTO
{
    public string $name;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
    }

    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}
```

## Service (Heredado y Transaccional)

```php
<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\DTO\SecurityContext;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Catalog\ProductServiceInterface;
use App\Repositories\GenericRepository;
use App\Models\ProductModel;
use App\Traits\AppliesQueryOptions;

class ProductService extends BaseCrudService implements ProductServiceInterface
{
    use AppliesQueryOptions;

    public function __construct(
        protected ProductModel $productModel,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($responseMapper);
        $this->repository = new GenericRepository($productModel);
    }

    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function() use ($request) {
            $id = $this->repository->insert($request->toArray());
            // Lógica de negocio adicional...
            return $this->mapToResponse($this->repository->find((int) $id));
        });
    }
}
```
