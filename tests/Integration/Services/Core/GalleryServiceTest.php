<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Core;

use App\DTO\Request\Common\GalleryAttachRequestDTO;
use App\DTO\Request\Common\GalleryReorderRequestDTO;
use App\Models\FileModel;
use App\Models\UserModel;
use App\Repositories\Files\FileRepository;
use App\Services\Core\GalleryService;
use Config\Database;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use Tests\Support\Fixtures\Gallery\GalleryFixtureModel;
use Tests\Support\Fixtures\Gallery\GalleryFixtureRepository;
use Tests\Support\IntegrationTestCase;

/**
 * @internal
 */
final class GalleryServiceTest extends IntegrationTestCase
{
    protected $refresh     = false;

    private GalleryService $service;
    private FileModel $fileModel;
    /** @var list<int> */
    private array $seededFileIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge();
        $forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'parent_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'file_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'sort_order' => ['type' => 'INT', 'null' => false, 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('gallery_test_pivots', true);

        $pivot     = new GalleryFixtureRepository(new GalleryFixtureModel());
        $files     = new FileRepository(new FileModel());
        $this->service   = new GalleryService($pivot, $files);
        $this->fileModel = new FileModel();

        // Per-test isolation: clear pivot rows AND seed fresh files.
        // Use emptyTable() (DELETE FROM) instead of truncate() for tables that
        // participate in a FK chain — MySQL blocks TRUNCATE on a referenced table
        // even when the referencing table is empty.
        Database::connect()->table('gallery_test_pivots')->truncate();
        Database::connect()->table('file_references')->emptyTable();
        Database::connect()->table('files')->emptyTable();
        $this->ensureOwnerExists();
        $this->seededFileIds = $this->seedFiles(3);
    }

    protected function tearDown(): void
    {
        Database::forge()->dropTable('gallery_test_pivots', true);
        parent::tearDown();
    }

    public function testAttachAssignsIncrementalSortOrder(): void
    {
        $first  = $this->service->attach(7, $this->attachRequest($this->seededFileIds[0]));
        $second = $this->service->attach(7, $this->attachRequest($this->seededFileIds[1]));

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
        $this->assertSame(7, $first->parent_id);
        $this->assertSame(7, $second->parent_id);
    }

    public function testAttachRespectsExplicitSortOrder(): void
    {
        $dto = $this->service->attach(7, new GalleryAttachRequestDTO([
            'file_id'    => (string) $this->seededFileIds[0],
            'sort_order' => 99,
        ], service('validation')));

        $this->assertSame(99, $dto->sort_order);
    }

    public function testListForReturnsOrderedAndEnrichedRows(): void
    {
        $this->service->attach(7, new GalleryAttachRequestDTO([
            'file_id'    => (string) $this->seededFileIds[0],
            'sort_order' => 2,
        ], service('validation')));
        $this->service->attach(7, new GalleryAttachRequestDTO([
            'file_id'    => (string) $this->seededFileIds[1],
            'sort_order' => 1,
        ], service('validation')));

        $list = $this->service->listFor(7);

        $this->assertCount(2, $list);
        $this->assertSame(1, $list[0]->sort_order);
        $this->assertSame((string) $this->seededFileIds[1], $list[0]->file_id);
        $this->assertSame('image-1.jpg', $list[0]->original_name);
        $this->assertTrue($list[0]->is_image);
    }

    public function testReorderUpdatesSortOrders(): void
    {
        $a = $this->service->attach(7, $this->attachRequest($this->seededFileIds[0]));
        $b = $this->service->attach(7, $this->attachRequest($this->seededFileIds[1]));

        $this->service->reorder(7, new GalleryReorderRequestDTO([
            'items' => [
                ['id' => $a->id, 'sort_order' => 10],
                ['id' => $b->id, 'sort_order' => 5],
            ],
        ], service('validation')));

        $list = $this->service->listFor(7);
        $this->assertSame((string) $this->seededFileIds[1], $list[0]->file_id);
        $this->assertSame(5, $list[0]->sort_order);
        $this->assertSame(10, $list[1]->sort_order);
    }

    public function testReorderIgnoresItemsBelongingToOtherParents(): void
    {
        $mine    = $this->service->attach(7, $this->attachRequest($this->seededFileIds[0]));
        $foreign = $this->service->attach(99, $this->attachRequest($this->seededFileIds[1]));

        $this->service->reorder(7, new GalleryReorderRequestDTO([
            'items' => [
                ['id' => $mine->id,    'sort_order' => 99],
                ['id' => $foreign->id, 'sort_order' => 99], // belongs to parent 99 — must be skipped
            ],
        ], service('validation')));

        $foreignRow = (new GalleryFixtureModel())->find($foreign->id);
        $this->assertNotNull($foreignRow);
        $this->assertSame(1, (int) $foreignRow->sort_order, 'Foreign parent row must not be touched.');
    }

    public function testDetachRemovesRow(): void
    {
        $attached = $this->service->attach(7, $this->attachRequest($this->seededFileIds[0]));

        $result = $this->service->detach(7, $attached->id);

        $this->assertTrue($result);
        $this->assertSame([], $this->service->listFor(7));
    }

    public function testDetachThrowsWhenPivotBelongsToOtherParent(): void
    {
        $foreign = $this->service->attach(99, $this->attachRequest($this->seededFileIds[0]));

        $this->expectException(NotFoundException::class);
        $this->service->detach(7, $foreign->id);
    }

    private function attachRequest(int $fileId): GalleryAttachRequestDTO
    {
        return new GalleryAttachRequestDTO(['file_id' => (string) $fileId], service('validation'));
    }

    private function ensureOwnerExists(): void
    {
        $users = new UserModel();
        if ($users->find(1) === null) {
            $users->insert([
                'id'         => 1,
                'email'      => 'gallery-owner@example.com',
                'password'   => password_hash('TestPass123!', PASSWORD_BCRYPT),
                'first_name' => 'Gallery',
                'last_name'  => 'Owner',
                'is_active'  => 1,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function seedFiles(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $rawId = $this->fileModel->insert([
                'user_id'        => 1,
                'original_name'  => sprintf('image-%d.jpg', $i),
                'stored_name'    => sprintf('stored-%d.jpg', $i),
                'mime_type'      => 'image/jpeg',
                'category'       => 'image',
                'size'           => 1024,
                'storage_driver' => 'local',
                'path'           => sprintf('/tmp/image-%d.jpg', $i),
                'uploaded_at'    => date('Y-m-d H:i:s'),
            ]);
            $ids[] = is_numeric($rawId) ? (int) $rawId : 0;
        }

        return $ids;
    }
}
