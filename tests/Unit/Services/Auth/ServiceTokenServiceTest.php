<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Entities\ApiKeyEntity;
use App\Entities\ApplicationEntity;
use App\Interfaces\Iam\ApplicationPermissionsResolverInterface;
use App\Interfaces\Tokens\ApiKeyRepositoryInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\ApplicationModel;
use App\Services\Auth\ServiceTokenService;
use App\Services\Tokens\Support\ApiKeyMaterialService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * ServiceTokenService Unit Tests
 */
class ServiceTokenServiceTest extends CIUnitTestCase
{
    protected ServiceTokenService $service;
    protected ApiKeyRepositoryInterface $mockApiKeyRepository;
    protected ApplicationModel $mockApplicationModel;
    protected ApplicationPermissionsResolverInterface $mockPermissionsResolver;
    protected JwtServiceInterface $mockJwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiKeyRepository = $this->createMock(ApiKeyRepositoryInterface::class);
        $this->mockApplicationModel = $this->createMock(ApplicationModel::class);
        $this->mockPermissionsResolver = $this->createMock(ApplicationPermissionsResolverInterface::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);

        $this->service = new ServiceTokenService(
            $this->mockApiKeyRepository,
            new ApiKeyMaterialService(),
            $this->mockApplicationModel,
            $this->mockPermissionsResolver,
            $this->mockJwtService,
            3600
        );
    }

    private function activeApiKey(int $applicationId = 5): ApiKeyEntity
    {
        return new ApiKeyEntity([
            'id' => 1,
            'application_id' => $applicationId,
            'is_active' => true,
        ]);
    }

    // ==================== issue() TESTS ====================

    public function testIssueThrowsWhenRawKeyIsEmpty(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->service->issue('');
    }

    public function testIssueThrowsWhenKeyNotFound(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn(null);

        $this->expectException(AuthorizationException::class);

        $this->service->issue('raw-key');
    }

    public function testIssueThrowsWhenKeyIsInactive(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn(new ApiKeyEntity([
            'id' => 1,
            'application_id' => 5,
            'is_active' => false,
        ]));

        $this->expectException(AuthorizationException::class);

        $this->service->issue('raw-key');
    }

    public function testIssueThrowsWhenKeyHasNoApplication(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn(new ApiKeyEntity([
            'id' => 1,
            'application_id' => null,
            'is_active' => true,
        ]));

        $this->expectException(AuthorizationException::class);

        $this->service->issue('raw-key');
    }

    public function testIssueThrowsWhenApplicationNotFound(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn($this->activeApiKey());
        $this->mockApplicationModel->method('find')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->issue('raw-key');
    }

    public function testIssueThrowsWhenApplicationCodeIsEmpty(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn($this->activeApiKey());
        $this->mockApplicationModel->method('find')->willReturn(new ApplicationEntity(['id' => 5, 'code' => '']));

        $this->expectException(NotFoundException::class);

        $this->service->issue('raw-key');
    }

    public function testIssueReturnsTokenResponseForValidKey(): void
    {
        $this->mockApiKeyRepository->method('findByHash')->willReturn($this->activeApiKey(5));
        $this->mockApplicationModel->method('find')->with(5)->willReturn(new ApplicationEntity(['id' => 5, 'code' => 'cms-domain']));
        $this->mockPermissionsResolver->method('resolve')->with(5)->willReturn(['pages.read', 'pages.write']);
        $this->mockJwtService
            ->expects($this->once())
            ->method('encodeServiceToken')
            ->with('service:cms-domain', ['pages.read', 'pages.write'], 3600)
            ->willReturn('service.jwt.token');

        $result = $this->service->issue('raw-key');

        $this->assertSame('service.jwt.token', $result->access_token);
        $this->assertSame('Bearer', $result->token_type);
        $this->assertSame(3600, $result->expires_in);
        $this->assertSame(['pages.read', 'pages.write'], $result->scope);
    }

    // ==================== issueByKeyId() TESTS ====================

    public function testIssueByKeyIdThrowsWhenKeyNotFound(): void
    {
        $this->mockApiKeyRepository->method('find')->willReturn(null);

        $this->expectException(AuthorizationException::class);

        $this->service->issueByKeyId(999);
    }

    public function testIssueByKeyIdThrowsWhenKeyIsInactive(): void
    {
        $this->mockApiKeyRepository->method('find')->willReturn(new ApiKeyEntity([
            'id' => 1,
            'application_id' => 5,
            'is_active' => false,
        ]));

        $this->expectException(AuthorizationException::class);

        $this->service->issueByKeyId(1);
    }

    public function testIssueByKeyIdThrowsWhenApplicationNotFound(): void
    {
        $this->mockApiKeyRepository->method('find')->willReturn($this->activeApiKey());
        $this->mockApplicationModel->method('find')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->issueByKeyId(1);
    }

    public function testIssueByKeyIdReturnsTokenResponseForValidKey(): void
    {
        $this->mockApiKeyRepository->method('find')->with(1)->willReturn($this->activeApiKey(7));
        $this->mockApplicationModel->method('find')->with(7)->willReturn(new ApplicationEntity(['id' => 7, 'code' => 'catalog-domain']));
        $this->mockPermissionsResolver->method('resolve')->with(7)->willReturn(['collection_items.read']);
        $this->mockJwtService
            ->method('encodeServiceToken')
            ->with('service:catalog-domain', ['collection_items.read'], 3600)
            ->willReturn('another.jwt.token');

        $result = $this->service->issueByKeyId(1);

        $this->assertSame('another.jwt.token', $result->access_token);
        $this->assertSame(['collection_items.read'], $result->scope);
    }
}
