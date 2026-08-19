<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

/**
 * Source-aware file usage read model for composed administrative clients.
 *
 * `source` contains one state per source plus an aggregate `state`; a partial
 * snapshot is never represented as complete. Usage rows retain optional
 * domain context so consumers do not need to repeat a domain query.
 */
final readonly class FileUsageSnapshotResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, string> $source
     * @param list<array<string, mixed>> $usages
     */
    public function __construct(
        public bool $complete,
        public array $source,
        public array $usages,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $source = [];
        foreach (($data['source'] ?? []) as $key => $state) {
            if (is_string($key) && is_string($state)) {
                $source[$key] = $state;
            }
        }

        $usages = [];
        foreach (($data['usages'] ?? []) as $usage) {
            if (is_array($usage)) {
                $usages[] = $usage;
            }
        }

        return new self(
            complete: ($data['complete'] ?? false) === true,
            source: $source,
            usages: $usages,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'complete' => $this->complete,
            'source' => $this->source,
            'usages' => $this->usages,
        ];
    }
}
