<?php

declare(strict_types=1);

namespace App\Libraries\Queue;

use dcardenasl\Ci4ApiCore\Queue\Job;
use dcardenasl\Ci4ApiCore\Queue\QueueManager;

/**
 * Queue manager that satisfies legacy QueueManager type-hints while executing
 * jobs synchronously. It is a thin compatibility adapter for services that
 * have not yet been generalized to QueueManagerInterface.
 */
class SyncCompatibleQueueManager extends QueueManager
{
    public function __construct()
    {
    }

    /**
     * @param class-string<Job> $job
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int
    {
        $this->run($job, $data);
        return 0;
    }

    /**
     * @param class-string<Job> $job
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int
    {
        $this->run($job, $data);
        return 0;
    }

    public function process(string $queue = 'default'): bool
    {
        return false;
    }

    /**
     * @return array{pending:int,processing:int,failed:int}
     */
    public function getStats(string $queue = 'default'): array
    {
        return ['pending' => 0, 'processing' => 0, 'failed' => 0];
    }

    /**
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     */
    private function run(string $job, array $data): void
    {
        $instance = new $job($data);
        $instance->setAttempts(1);

        try {
            $instance->handle();
        } catch (\Throwable $e) {
            $instance->failed($e);
            throw $e;
        }
    }
}
