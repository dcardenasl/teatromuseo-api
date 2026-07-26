<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Queue\QueueManager;
use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditService;
use dcardenasl\Ci4ApiCore\Services\Audit\AuditWriter;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * AuditService Unit Tests
 *
 * Tests audit logging functionality with mocked dependencies.
 */
class AuditServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected AuditService $service;
    protected AuditRepositoryInterface $mockAuditRepository;
    protected ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        parent::setUp();

        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = true;

        $this->mockAuditRepository = $this->createMock(AuditRepositoryInterface::class);

        // Mock UserModel via factory to satisfy defensive existence checks
        $mockUserModel = $this->createMock(\App\Models\UserModel::class);
        $mockUserModel->method('find')->willReturn((object)['id' => 99]);
        \CodeIgniter\Config\Factories::injectMock('models', \App\Models\UserModel::class, $mockUserModel);

        $this->responseMapper = new class () implements ResponseMapperInterface {
            public function map(object|array $source): DataTransferObjectInterface
            {
                if (is_array($source)) {
                    return \App\DTO\Response\Audit\AuditResponseDTO::fromArray($source);
                }
                $data = method_exists($source, 'toArray') ? $source->toArray() : (array) $source;
                return \App\DTO\Response\Audit\AuditResponseDTO::fromArray($data);
            }
        };

        $this->service = new AuditService($this->mockAuditRepository, $this->responseMapper);
    }

    protected function tearDown(): void
    {
        \dcardenasl\Ci4ApiCore\Services\Audit\AuditService::$forceEnabledInTests = false;
        parent::tearDown();
    }

    // ==================== LOG TESTS ====================

    public function testLogInsertsAuditRecord(): void
    {
        $context = new SecurityContext(99, ['ip_address' => '127.0.0.1']);

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'create'
                    && $data['entity_type'] === 'users'
                    && $data['entity_id'] === 1
                    && $data['user_id'] === 99
                    && $data['ip_address'] === '127.0.0.1';
            }));

        $this->service->log(
            'create',
            'users',
            1,
            [],
            ['email' => 'newuser@example.com'],
            $context
        );
    }

    public function testLogEncodesValuesAsJson(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'old@example.com'];
        $newValues = ['email' => 'new@example.com'];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldValues, $newValues) {
                return $data['old_values'] === json_encode($oldValues)
                    && $data['new_values'] === json_encode($newValues);
            }));

        $this->service->log(
            'update',
            'users',
            1,
            $oldValues,
            $newValues,
            $context
        );
    }

    public function testLogRemovesSensitiveFieldsFromAuditPayload(): void
    {
        $context = new SecurityContext(99);
        $oldValues = [
            'email' => 'old@example.com',
            'password' => 'old-secret',
            'profile' => [
                'token' => 'token-old',
                'timezone' => 'UTC',
            ],
        ];
        $newValues = [
            'email' => 'new@example.com',
            'password' => 'new-secret',
            'access_token' => 'jwt-token',
            'profile' => [
                'refresh_token' => 'refresh-token',
                'timezone' => 'America/Mexico_City',
            ],
        ];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $old = json_decode((string) $data['old_values'] ?: '{}', true);
                $new = json_decode((string) $data['new_values'] ?: '{}', true);

                return !isset($old['password'])
                    && !isset($old['profile']['token'])
                    && !isset($new['password'])
                    && !isset($new['access_token'])
                    && !isset($new['profile']['refresh_token'])
                    && ($old['email'] ?? null) === 'old@example.com'
                    && ($new['email'] ?? null) === 'new@example.com';
            }));

        $this->service->log(
            'update',
            'users',
            1,
            $oldValues,
            $newValues,
            $context
        );
    }

    public function testLogPersistsControlFieldsAndSanitizedMetadata(): void
    {
        $context = new SecurityContext(99, [
            'ip_address' => '10.0.0.10',
            'user_agent' => 'PHPUnit',
            'request_id' => 'req-test-123',
        ]);

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $metadata = json_decode((string) ($data['metadata'] ?? '{}'), true);

                return ($data['result'] ?? null) === 'denied'
                    && ($data['severity'] ?? null) === 'critical'
                    && ($data['request_id'] ?? null) === 'req-test-123'
                    && !isset($metadata['token'])
                    && ($metadata['scope'] ?? null) === 'api_key';
            }));

        $this->service->log(
            'api_key_rate_limit_exceeded',
            'api_keys',
            12,
            [],
            [],
            $context,
            'denied',
            'critical',
            ['scope' => 'api_key', 'token' => 'secret-token']
        );
    }

    public function testLogEnqueuesNonCriticalEventWhenAsyncEnabled(): void
    {
        $context = new SecurityContext(99, ['ip_address' => '127.0.0.1']);
        $queueManager = $this->createMock(QueueManager::class);
        $auditConfig = new \Config\Audit();
        $auditConfig->asyncEnabled = true;
        $auditConfig->queueName = 'audit';

        $this->mockAuditRepository
            ->expects($this->never())
            ->method('insert');

        $queueManager->expects($this->once())
            ->method('push')
            ->with(
                \dcardenasl\Ci4ApiCore\Queue\Jobs\WriteAuditLogJob::class,
                $this->callback(static function (array $data): bool {
                    return isset($data['audit']) && is_array($data['audit']);
                }),
                'audit'
            )
            ->willReturn(10);

        $service = new AuditService(
            $this->mockAuditRepository,
            $this->responseMapper,
            new AuditWriter($this->mockAuditRepository),
            $queueManager,
            $auditConfig
        );

        $service->log('create', 'users', 1, [], ['email' => 'test@example.com'], $context);
    }

    public function testLogPersistsCriticalEventSynchronouslyWhenAsyncEnabled(): void
    {
        $context = new SecurityContext(99, ['ip_address' => '127.0.0.1']);
        $queueManager = $this->createMock(QueueManager::class);
        $auditConfig = new \Config\Audit();
        $auditConfig->asyncEnabled = true;

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert');

        $queueManager->expects($this->never())
            ->method('push');

        $service = new AuditService(
            $this->mockAuditRepository,
            $this->responseMapper,
            new AuditWriter($this->mockAuditRepository),
            $queueManager,
            $auditConfig
        );

        $service->log(
            'api_key_auth_failed',
            'api_keys',
            null,
            [],
            ['scope' => 'api_key'],
            $context,
            'failure',
            'critical'
        );
    }

    public function testLogFallsBackToSyncWhenQueueFails(): void
    {
        $context = new SecurityContext(99, ['ip_address' => '127.0.0.1']);
        $queueManager = $this->createMock(QueueManager::class);
        $auditConfig = new \Config\Audit();
        $auditConfig->asyncEnabled = true;

        $queueManager->expects($this->once())
            ->method('push')
            ->willThrowException(new \RuntimeException('queue down'));

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert');

        $service = new AuditService(
            $this->mockAuditRepository,
            $this->responseMapper,
            new AuditWriter($this->mockAuditRepository),
            $queueManager,
            $auditConfig
        );

        $service->log('update', 'users', 1, ['a' => 1], ['a' => 2], $context);
    }

    // ==================== LOG CREATE TESTS ====================

    public function testLogCreateLogsWithEmptyOldValues(): void
    {
        $context = new SecurityContext(99);
        $newData = ['first_name' => 'New', 'email' => 'new@example.com'];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($newData) {
                return $data['action'] === 'create'
                    && $data['old_values'] === null
                    && $data['new_values'] === json_encode($newData);
            }));

        $this->service->logCreate('users', 1, $newData, $context);
    }

    // ==================== LOG UPDATE TESTS ====================

    public function testLogUpdateOnlyLogsIfValuesChanged(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'same@example.com'];
        $newValues = ['email' => 'same@example.com'];

        // insert should NOT be called when values are the same
        $this->mockAuditRepository
            ->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    public function testLogUpdateLogsWhenValuesAreDifferent(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'old@example.com'];
        $newValues = ['email' => 'new@example.com'];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'update';
            }));

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    public function testLogUpdateDoesNotLogWhenOnlySensitiveValuesChanged(): void
    {
        $context = new SecurityContext(99);
        $oldValues = [
            'email' => 'same@example.com',
            'password' => 'old-secret',
            'profile' => ['token' => 'old-token'],
        ];
        $newValues = [
            'email' => 'same@example.com',
            'password' => 'new-secret',
            'profile' => ['token' => 'new-token'],
        ];

        $this->mockAuditRepository
            ->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    // ==================== LOG DELETE TESTS ====================

    public function testLogDeleteLogsWithEmptyNewValues(): void
    {
        $context = new SecurityContext(99);
        $oldData = ['first_name' => 'Deleted', 'email' => 'deleted@example.com'];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldData) {
                return $data['action'] === 'delete'
                    && $data['old_values'] === json_encode($oldData)
                    && $data['new_values'] === null;
            }));

        $this->service->logDelete('users', 1, $oldData, $context);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsAuditLog(): void
    {
        $log = $this->createAuditLogEntity([
            'id' => 1,
            'user_id' => 99,
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => 1,
            'old_values' => null,
            'new_values' => '{"first_name":"Test"}',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($log);

        $result = $this->service->show(1);

        $this->assertInstanceOf(\App\DTO\Response\Audit\AuditResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('create', $data['action']);
    }

    public function testShowWithNonExistentIdThrowsNotFoundException(): void
    {
        $this->mockAuditRepository
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(999);
    }

    // ==================== BY ENTITY TESTS ====================

    public function testByEntityReturnsLogsForEntity(): void
    {
        $logs = [
            $this->createAuditLogEntity([
                'id' => 1,
                'user_id' => 99,
                'action' => 'create',
                'entity_type' => 'users',
                'entity_id' => 5,
                'old_values' => null,
                'new_values' => '{"test":"data"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'created_at' => '2024-01-01 00:00:00',
            ]),
            $this->createAuditLogEntity([
                'id' => 2,
                'user_id' => 99,
                'action' => 'update',
                'entity_type' => 'users',
                'entity_id' => 5,
                'old_values' => '{"old":"value"}',
                'new_values' => '{"new":"value"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'created_at' => '2024-01-01 01:00:00',
            ]),
        ];

        $this->mockAuditRepository
            ->expects($this->once())
            ->method('getByEntity')
            ->with('users', 5)
            ->willReturn($logs);

        $result = $this->service->byEntity(new \App\DTO\Request\Audit\AuditByEntityRequestDTO([
            'entity_type' => 'users',
            'entity_id' => 5,
        ], service('validation')));
        $payload = $result->toArray();

        $this->assertInstanceOf(\dcardenasl\Ci4ApiCore\Dto\Common\PayloadResponseDTO::class, $result);
        $this->assertCount(2, $payload);
        $this->assertIsArray($payload[0]);
        $this->assertSame('create', $payload[0]['action'] ?? null);
    }

    public function testByEntityNormalizesSingularEntityType(): void
    {
        $this->mockAuditRepository
            ->expects($this->once())
            ->method('getByEntity')
            ->with('users', 5)
            ->willReturn([]);

        $result = $this->service->byEntity(new \App\DTO\Request\Audit\AuditByEntityRequestDTO([
            'entity_type' => 'user',
            'entity_id' => 5,
        ], service('validation')));

        $this->assertSame([], $result->toArray());
    }

    public function testByEntityWithMissingParamsThrowsValidationException(): void
    {
        $this->expectException(\dcardenasl\Ci4ApiCore\Exceptions\ValidationException::class);
        new \App\DTO\Request\Audit\AuditByEntityRequestDTO(['entity_type' => 'users'], service('validation'));
    }

    // ==================== HELPER METHODS ====================

    private function createAuditLogEntity(array $data): \App\Entities\AuditLogEntity
    {
        return new \App\Entities\AuditLogEntity($data);
    }
}
