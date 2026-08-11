<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use App\Services\Admin\DashboardSummaryService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * @internal
 */
final class DashboardSummaryServiceTest extends CIUnitTestCase
{
    public function testOnlyPermittedHubSectionsAreReturned(): void
    {
        $repository = $this->createMock(DashboardSummaryRepositoryInterface::class);
        $repository->expects($this->once())->method('read')->willReturn([
            'users_total' => 10,
            'files_total' => 20,
            'recent_files' => [['id' => 5]],
            'metrics' => ['request_stats' => ['total' => 3]],
        ]);

        $result = (new DashboardSummaryService($repository))->read(
            new SecurityContext(7, [], ['files.read'])
        );

        $this->assertSame([
            'files' => [
                'total' => 20,
                'recent' => [['id' => 5]],
            ],
        ], $result->sections);
    }

    public function testNoRelevantPermissionDoesNotReadRepository(): void
    {
        $repository = $this->createMock(DashboardSummaryRepositoryInterface::class);
        $repository->expects($this->never())->method('read');

        $result = (new DashboardSummaryService($repository))->read(
            new SecurityContext(7, [], ['cms.pages.read'])
        );

        $this->assertSame([], $result->sections);
    }
}
