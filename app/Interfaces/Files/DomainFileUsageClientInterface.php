<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

interface DomainFileUsageClientInterface
{
    /**
     * Ask every configured domain app whether it references the given file.
     * Fail-open: a domain that is unreachable or errors is logged and
     * treated as "no usages reported" — it never blocks a Hub file
     * operation on its own. Returns the shared usages contract shape.
     *
     * @return list<array{source: string, resource: string, resource_id: int, label: string|null, role: string}>
     */
    public function collectUsages(int $fileId): array;

    /**
     * Ask every configured domain app for usages and preserve source health
     * plus optional domain context for composed administrative reads.
     *
     * @return array{
     *     complete: bool,
     *     sources: array<string, 'ok'|'unavailable'>,
     *     usages: list<array<string, mixed>>
     * }
     */
    public function collectUsageSnapshot(int $fileId): array;

    /**
     * Best-effort notification to every configured domain app that a file's
     * cached metadata (URL/variants) is stale and should be dropped. Never
     * throws — failures are logged only, since the file operation that
     * triggered this has already succeeded and must not be rolled back
     * over a notification failure.
     */
    public function broadcastInvalidate(int $fileId): void;
}
