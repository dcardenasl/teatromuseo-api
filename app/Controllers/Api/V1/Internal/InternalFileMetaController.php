<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Internal;

use App\Models\FileModel;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Internal M2M endpoint for resolving file public metadata.
 *
 * Called by trusted Domain apps (via X-App-Key) to resolve file IDs to their
 * public URLs and variant maps without requiring a user JWT. Returns only the
 * fields needed for rendering: id, url, and variants.
 */
class InternalFileMetaController extends ApiController
{
    protected function resolveDefaultService(): object
    {
        return model(FileModel::class);
    }

    /**
     * Batch-resolve public metadata for a set of file IDs.
     *
     * Query params:
     *   ids[] — list of integer file IDs (max 200)
     *
     * Response data: array keyed by file ID, each value: {id, url, variants}
     */
    public function batchMeta(): ResponseInterface
    {
        return $this->handleRequest(function (): mixed {
            $raw = $this->request->getVar('ids');
            $ids = is_array($raw) ? $raw : (is_string($raw) ? explode(',', $raw) : []);

            $ids = array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn (int $id): bool => $id > 0
            )));

            if (empty($ids)) {
                return (object) [];
            }

            $ids = array_slice($ids, 0, 200);

            /** @var FileModel $model */
            $model = model(FileModel::class);
            $rows  = $model
                ->select('id, url, variants')
                ->whereIn('id', $ids)
                ->where('deleted_at IS NULL')
                ->asArray()
                ->findAll();

            $result = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $idRaw  = $row['id'] ?? 0;
                $fileId = is_scalar($idRaw) ? (int) $idRaw : 0;
                if ($fileId <= 0) {
                    continue;
                }

                $raw      = $row['variants'] ?? null;
                $variants = null;
                if (is_string($raw) && $raw !== '') {
                    $decoded  = json_decode($raw, true);
                    $variants = is_array($decoded) ? $decoded : null;
                }

                $urlRaw = $row['url'] ?? null;
                $url    = is_scalar($urlRaw) ? (string) $urlRaw : null;

                $result[$fileId] = [
                    'id'       => $fileId,
                    'url'      => $url,
                    'variants' => $variants ?? [],
                ];
            }

            return $result;
        });
    }
}
