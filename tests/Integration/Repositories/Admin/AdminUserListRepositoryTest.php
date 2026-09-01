<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories\Admin;

use App\Repositories\Users\AdminUserListRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class AdminUserListRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testAdminUserListProjectionExecutesAsSinglePaginatedRead(): void
    {
        $result = (new AdminUserListRepository($this->db))->paginateAdminList([
            'exclude_superadmins' => true,
        ], 1, 20);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['per_page']);
    }
}
