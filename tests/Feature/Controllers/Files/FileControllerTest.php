<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Files;

use App\Models\FileModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class FileControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected FileModel $fileModel;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileModel = new FileModel();
        $identity = $this->actAs('user');
        $this->token = $identity['token'];
    }

    public function testListFilesRequiresAuth(): void
    {
        $this->resetState(); // Ensure clean state
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::flush();
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/files');

        $result->assertStatus(401);
    }

    public function testListFilesReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/files');

        $result->assertStatus(200);
    }

    public function testGetFileReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/files/{$fileId}");

        $result->assertStatus(200);
    }

    public function testGetFileInfoReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/files/{$fileId}/info");

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
        $this->assertEquals($fileId, $json['data']['id']);
    }

    public function testGetFileUsagesReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/files/{$fileId}/usages");

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);
        $this->assertIsArray($json['data']);
    }

    public function testUpdateFileMetadataReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patch("/api/v1/files/{$fileId}", [
                'alt_text' => 'Updated Alt Text',
                'caption'  => 'Updated Caption',
                'credit'   => 'Updated Credit',
            ]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);
        $this->assertEquals('Updated Alt Text', $json['data']['alt_text']);
        $this->assertEquals('Updated Caption', $json['data']['caption']);
        $this->assertEquals('Updated Credit', $json['data']['credit']);
    }

    public function testUpdateFileMetadataFailsWithEmptyData(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->patch("/api/v1/files/{$fileId}", []);

        $result->assertStatus(400); // Handled by DTO map throwing BadRequestException
    }

    public function testRegenerateVariantsOnNonImageReturns400(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId, 'application/pdf');

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post("/api/v1/files/{$fileId}/regenerate-variants");

        $result->assertStatus(400);
    }

    public function testReplaceFileRequiresBinary(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post("/api/v1/files/{$fileId}/replace", []);

        $result->assertStatus(400); // BadRequestException: no file in payload
    }

    public function testDeleteFileReturnsSuccess(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}");

        $result->assertStatus(200);
    }

    public function testDeleteSoftDeletesAndPreservesRow(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}")
            ->assertStatus(200);

        $this->assertNull($this->fileModel->find($fileId), 'Default find should hide trashed file');

        $trashed = $this->fileModel->withDeleted()->find($fileId);
        $this->assertNotNull($trashed, 'Row is still present (just soft-deleted)');
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($this->currentUserId, (int) $trashed->deleted_by_user_id);
    }

    public function testSecondDeleteOnTrashedFileReturns404(): void
    {
        // Once trashed, the file is invisible to default queries, so a second
        // soft-delete attempt sees "not found" before reaching the trash check.
        // This is the intended REST semantics — the resource has been deleted
        // from the caller's perspective.
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}")
            ->assertStatus(404);
    }

    public function testRestoreBringsFileBack(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post("/api/v1/files/{$fileId}/restore")
            ->assertStatus(200);

        $row = $this->fileModel->find($fileId);
        $this->assertNotNull($row, 'Restored row visible in default queries');
        $this->assertNull($row->deleted_at);
        $this->assertNull($row->deleted_by_user_id);
    }

    public function testRestoreFailsWhenNotTrashed(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post("/api/v1/files/{$fileId}/restore")
            ->assertStatus(400);
    }

    public function testForceDeletePurgesTrashedFile(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}/force")
            ->assertStatus(200);

        $this->assertNull(
            $this->fileModel->withDeleted()->find($fileId),
            'Force-delete must wipe the row entirely (not even withDeleted finds it)'
        );
    }

    public function testForceDeleteFailsForNonTrashedFile(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $fileId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}/force")
            ->assertStatus(400);
    }

    public function testListWithTrashedOnlyShowsOnlyTrashed(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $liveId    = $this->createFile($this->currentUserId);
        $trashedId = $this->createFile($this->currentUserId);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$trashedId}")
            ->assertStatus(200);

        $resp = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/files?trashed=only');
        $resp->assertStatus(200);
        $body = json_decode((string) $resp->getJSON(), true);
        $ids  = array_column($body['data'] ?? [], 'id');
        $this->assertContains($trashedId, $ids);
        $this->assertNotContains($liveId, $ids);
    }

    public function testBulkDeleteReturnsPerItemOutcomes(): void
    {
        \dcardenasl\Ci4ApiCore\Http\ContextHolder::set(new \dcardenasl\Ci4ApiCore\Dto\SecurityContext($this->currentUserId, [], \App\Support\TestPermissionResolver::permissionsForRole((string) $this->currentUserRole)));
        $a = $this->createFile($this->currentUserId);
        $b = $this->createFile($this->currentUserId);
        $missing = 999_999;

        // Ids are sent as strings on purpose: CI4's `InvalidChars` global
        // filter throws `TypeError` from `mb_check_encoding()` when it
        // recurses into a JSON body containing raw integers (framework
        // limitation). DTO casts back to int internally.
        $resp = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->post('/api/v1/files/bulk-delete', [
                'ids' => array_map('strval', [$a, $b, $missing]),
            ]);
        $resp->assertStatus(200);

        $body  = json_decode((string) $resp->getJSON(), true);
        $items = $body['data'] ?? [];
        $byId  = [];
        foreach ($items as $item) {
            $byId[(int) $item['id']] = $item;
        }

        $this->assertTrue($byId[$a]['ok'] ?? false);
        $this->assertTrue($byId[$b]['ok'] ?? false);
        $this->assertFalse($byId[$missing]['ok'] ?? true);
        $this->assertArrayHasKey('error', $byId[$missing]);
    }

    private function createFile(int $userId, string $mimeType = 'image/jpeg'): int
    {
        return (int) $this->fileModel->insert([
            'user_id' => $userId,
            'original_name' => 'example.pdf',
            'stored_name' => 'example_123.pdf',
            'mime_type' => $mimeType,
            'size' => 1234,
            'storage_driver' => 's3',
            'path' => '2024/01/01/example_123.pdf',
            'url' => 'https://example.com/example_123.pdf',
            'metadata' => json_encode(['extension' => 'pdf']),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
