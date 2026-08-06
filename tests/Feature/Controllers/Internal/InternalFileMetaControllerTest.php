<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Internal;

use App\Models\FileModel;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * HTTP tests for InternalFileMetaController (LAYER-07: previously zero test
 * coverage; also the controller LAYER-02 fixed off `model(FileModel::class)`
 * onto Services::fileService()->resolvePublicMetaBatch()).
 *
 * @internal
 */
final class InternalFileMetaControllerTest extends ApiTestCase
{
    use AuthTestTrait;


    public function testBatchMetaRequiresAppKey(): void
    {
        $this->clearTestRequestHeaders();

        $result = $this->get('/api/v1/internal/files/batch-meta?ids[]=1');

        $result->assertStatus(401);
    }

    public function testBatchMetaRejectsInvalidAppKey(): void
    {
        $result = $this->withHeaders(['X-App-Key' => 'not-a-real-key'])
            ->get('/api/v1/internal/files/batch-meta?ids[]=1');

        $result->assertStatus(403);
    }

    public function testBatchMetaWithNoIdsReturnsEmptyObject(): void
    {
        $rawKey = $this->seedAppAndKey('file-meta-empty');

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta');

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status']);
        $this->assertSame([], $json['data']);
    }

    public function testBatchMetaWithUnknownIdsReturnsEmptyObject(): void
    {
        $rawKey = $this->seedAppAndKey('file-meta-unknown');

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta?ids[]=999999');

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame([], $json['data']);
    }

    public function testBatchMetaResolvesUrlAndVariantsForKnownFile(): void
    {
        $rawKey = $this->seedAppAndKey('file-meta-known');
        $fileId = $this->insertFile('uploads/original/test-image.jpg', [
            'thumb' => ['path' => 'uploads/variants/test-image-thumb.jpg', 'width' => 100, 'height' => 100],
        ]);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get("/api/v1/internal/files/batch-meta?ids[]={$fileId}");

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status']);

        $entry = $json['data'][(string) $fileId] ?? null;
        $this->assertNotNull($entry, 'Response must be keyed by file id');
        $this->assertSame($fileId, $entry['id']);
        $this->assertIsString($entry['url']);
        $this->assertStringContainsString('test-image.jpg', $entry['url']);
        $this->assertArrayHasKey('thumb', $entry['variants']);
        $this->assertIsString($entry['variants']['thumb']['url']);
        $this->assertStringContainsString('test-image-thumb.jpg', $entry['variants']['thumb']['url']);
    }

    public function testBatchMetaExcludesSoftDeletedFiles(): void
    {
        $rawKey = $this->seedAppAndKey('file-meta-deleted');
        $fileId = $this->insertFile('uploads/original/deleted-image.jpg', null, deleted: true);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get("/api/v1/internal/files/batch-meta?ids[]={$fileId}");

        $result->assertOK();
        $json = $this->getResponseJson($result);
        $this->assertSame([], $json['data']);
    }

    /**
     * @param array<string, mixed>|null $variants
     */
    private function insertFile(string $path, ?array $variants = null, bool $deleted = false): int
    {
        $userId = $this->createUser('file-meta-owner-' . uniqid() . '@example.test', 'ValidPass123!', 'user');

        $fileModel = new FileModel();
        $fileId = (int) $fileModel->insert([
            'user_id'        => $userId,
            'original_name'  => basename($path),
            'stored_name'    => basename($path),
            'mime_type'      => 'image/jpeg',
            'size'           => 1024,
            'storage_driver' => 'local',
            'path'           => $path,
            'url'            => null,
            'variants'       => $variants !== null ? json_encode($variants) : null,
            'width'          => 200,
            'height'         => 200,
            'uploaded_at'    => date('Y-m-d H:i:s'),
        ]);

        if ($deleted) {
            $fileModel->delete($fileId);
        }

        return $fileId;
    }

    private function seedAppAndKey(string $appCode): string
    {
        $db = \Config\Database::connect();

        $db->table('applications')->insert([
            'code'       => $appCode,
            'name'       => ucfirst($appCode),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $appId = (int) $db->insertID();

        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        $db->table('api_keys')->insert([
            'application_id'      => $appId,
            'name'                => "{$appCode}-key",
            'key_prefix'          => substr($rawKey, 0, 8),
            'key_hash'            => $material->hash($rawKey),
            'is_active'           => 1,
            'rate_limit_requests' => 600,
            'rate_limit_window'   => 60,
            'user_rate_limit'     => 60,
            'ip_rate_limit'       => 200,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        return $rawKey;
    }
}
