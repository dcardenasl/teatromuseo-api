<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Internal;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
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
        return Services::fileService();
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
            $ids = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                array_filter($ids, static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0),
            )));

            if (count($ids) > 200) {
                throw new BadRequestException(lang('Files.max_batch_ids'), [
                    'ids' => [lang('Files.max_batch_ids_hint')],
                ]);
            }

            $result = Services::fileService()->resolvePublicMetaBatch($ids);
            if (empty($result)) {
                return (object) [];
            }

            return $result;
        });
    }
}
