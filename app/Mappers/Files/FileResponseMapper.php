<?php

declare(strict_types=1);

namespace App\Mappers\Files;

use App\DTO\Response\Files\FileResponseDTO;
use App\Libraries\Storage\StorageManager;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;

/**
 * Maps file records while resolving public URLs from storage paths.
 */
final class FileResponseMapper implements ResponseMapperInterface
{
    private DtoResponseMapper $delegate;

    public function __construct(private readonly StorageManager $storage)
    {
        $this->delegate = new DtoResponseMapper(FileResponseDTO::class);
    }

    public function map(object|array $source): DataTransferObjectInterface
    {
        $data = is_array($source) ? $source : $source->toArray();
        $path = isset($data['path']) ? trim((string) $data['path']) : '';

        if ($path !== '') {
            $data['url'] = $this->storage->url($path);
        }

        $variants = $data['variants'] ?? null;
        if (is_string($variants) && $variants !== '') {
            $variants = json_decode($variants, true);
        }

        if (is_array($variants)) {
            foreach ($variants as $key => $variant) {
                if (is_array($variant) && isset($variant['path'])) {
                    $variants[$key]['url'] = $this->storage->url((string) $variant['path']);
                }
            }
            $data['variants'] = $variants;
        }

        return $this->delegate->map($data);
    }
}
