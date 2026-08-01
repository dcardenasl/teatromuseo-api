<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\DTO\Response\ApiKeys\ApiKeyResponseDTO;
use App\Entities\ApiKeyEntity;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Services\Tokens\Actions\CreateApiKeyAction;
use App\Services\Tokens\Actions\UpdateApiKeyAction;
use App\Services\Tokens\ApiKeyService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use Tests\Support\Traits\CustomAssertionsTrait;

class FakeApiKeyRepository implements ApiKeyRepositoryInterface
{
    public ?ApiKeyEntity $returnEntity = null;
    public int|false $insertReturn    = 1;
    public bool $updateReturn         = true;
    public bool $deleteReturn         = true;
    public array $validationErrors    = [];

    public function findByHash(string $hash): ?ApiKeyEntity
    {
        return $this->returnEntity;
    }

    public function find(int|string $id): ?object
    {
        return $this->returnEntity;
    }

    public function findBy(string $column, mixed $value): ?object
    {
        return $this->returnEntity;
    }

    public function setEntityContext(int|string $id, object|array $entity): void
    {
    }

    public function errors(): array
    {
        return $this->validationErrors;
    }

    public function findAll(?int $limit = null, int $offset = 0): array
    {
        return [];
    }

    public function findByIds(array $ids): array
    {
        return [];
    }

    public function insert(array|object $data, bool $returnID = true): int|string|bool
    {
        return $this->insertReturn;
    }

    public function update(int|string|array $id = null, array|object|null $data = null): bool
    {
        return $this->updateReturn;
    }

    public function delete(int|string|array $id = null, bool $purge = false): bool
    {
        return $this->deleteReturn;
    }

    public function restore(int|string $id, array $data = []): bool
    {
        return true;
    }

    public function withAuditAction(string $action): static
    {
        return $this;
    }

    public function getModel(): \CodeIgniter\Model
    {
        throw new \RuntimeException('Not used in test');
    }

    public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array
    {
        return [
            'data' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}

/**
 * ApiKeyService Unit Tests
 */
class ApiKeyServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected ApiKeyService $service;
    protected FakeApiKeyRepository $mockApiKeyRepository;
    protected CreateApiKeyAction $mockCreateApiKeyAction;
    protected UpdateApiKeyAction $mockUpdateApiKeyAction;
    protected ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiKeyRepository = new FakeApiKeyRepository();
        $this->mockCreateApiKeyAction = $this->createMock(CreateApiKeyAction::class);
        $this->mockUpdateApiKeyAction = $this->createMock(UpdateApiKeyAction::class);
        $this->responseMapper = new class () implements ResponseMapperInterface {
            public function map(object|array $source): DataTransferObjectInterface
            {
                $data = is_array($source) ? $source : $source->toArray();
                return ApiKeyResponseDTO::fromArray($data);
            }
        };

        $this->service = new ApiKeyService(
            $this->mockApiKeyRepository,
            $this->responseMapper,
            $this->mockCreateApiKeyAction,
            $this->mockUpdateApiKeyAction
        );
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsApiKeyData(): void
    {
        $entity = $this->makeEntity(['id' => 1, 'name' => 'Test Key', 'key_prefix' => 'apk_abc123de']);
        $this->mockApiKeyRepository->returnEntity = $entity;

        $result = $this->service->show(1);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $this->assertEquals(1, $result->toArray()['id']);
        $this->assertEquals('Test Key', $result->toArray()['name']);
    }

    public function testShowNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyRepository->returnEntity = null;
        $this->expectException(NotFoundException::class);
        $this->service->show(999);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesApiKeyAndReturnsRawKey(): void
    {
        $entity = $this->makeEntity([
            'id'                   => 1,
            'name'                 => 'My App',
            'key_prefix'           => 'apk_',
            'is_active'            => 1,
            'rate_limit_requests'  => 600,
        ]);
        $request = new ApiKeyCreateRequestDTO(['name' => 'My App'], service('validation'));
        $this->mockCreateApiKeyAction->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(ApiKeyCreateRequestDTO::class))
            ->willReturn(['entity' => $entity, 'key' => 'apk_test_key']);

        $result = $this->service->store($request);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $data = $result->toArray();
        $this->assertArrayHasKey('key', $data);
        $this->assertStringStartsWith('apk_', $data['key']);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesApiKey(): void
    {
        $entity = $this->makeEntity(['id' => 1, 'name' => 'New Name', 'is_active' => 1]);
        $this->mockUpdateApiKeyAction->expects($this->once())
            ->method('execute')
            ->with(1, $this->isInstanceOf(ApiKeyUpdateRequestDTO::class))
            ->willReturn($entity);

        $request = new ApiKeyUpdateRequestDTO(['name' => 'New Name'], service('validation'));
        $result  = $this->service->update(1, $request);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $this->assertEquals('New Name', $result->toArray()['name']);
    }

    public function testUpdateNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockUpdateApiKeyAction->method('execute')->willThrowException(new NotFoundException(lang('Api.resourceNotFound')));
        $this->expectException(NotFoundException::class);
        $this->service->update(99, new ApiKeyUpdateRequestDTO(['name' => 'X'], service('validation')));
    }

    public function testUpdateWithNoFieldsThrowsBadRequestException(): void
    {
        $this->mockUpdateApiKeyAction->method('execute')->willThrowException(new BadRequestException(lang('Api.noFieldsToUpdate')));
        $this->expectException(BadRequestException::class);
        $this->service->update(1, new ApiKeyUpdateRequestDTO([], service('validation')));
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesApiKey(): void
    {
        $this->mockApiKeyRepository->returnEntity = $this->makeEntity(['id' => 1, 'name' => 'To Delete']);
        $result = $this->service->destroy(1);
        $this->assertTrue($result);
    }

    public function testDestroyNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyRepository->returnEntity = null;
        $this->expectException(NotFoundException::class);
        $this->service->destroy(99);
    }

    // ==================== HELPER ====================

    private function makeEntity(array $data): ApiKeyEntity
    {
        $entity = new ApiKeyEntity();
        foreach ($data as $key => $value) {
            $entity->$key = $value;
        }
        return $entity;
    }
}
