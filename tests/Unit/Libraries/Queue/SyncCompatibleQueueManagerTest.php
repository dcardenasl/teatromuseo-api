<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries\Queue;

use App\Libraries\Queue\SyncCompatibleQueueManager;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Queue\Job;

final class SyncCompatibleQueueManagerTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        RecordingJob::$handled = [];
        RecordingJob::$shouldFail = false;
        parent::tearDown();
    }

    public function testPushRunsTheJobSynchronouslyAndReturnsZero(): void
    {
        $manager = new SyncCompatibleQueueManager();

        $result = $manager->push(RecordingJob::class, ['foo' => 'bar']);

        $this->assertSame(0, $result);
        $this->assertSame([['foo' => 'bar']], RecordingJob::$handled);
    }

    public function testLaterRunsTheJobSynchronouslyIgnoringDelay(): void
    {
        $manager = new SyncCompatibleQueueManager();

        $result = $manager->later(300, RecordingJob::class, ['baz' => 'qux']);

        $this->assertSame(0, $result);
        $this->assertSame([['baz' => 'qux']], RecordingJob::$handled);
    }

    public function testPushPropagatesExceptionAndMarksJobFailed(): void
    {
        RecordingJob::$shouldFail = true;

        $manager = new SyncCompatibleQueueManager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        try {
            $manager->push(RecordingJob::class, []);
        } finally {
            $this->assertTrue(RecordingJob::$failedWasCalled);
        }
    }

    public function testProcessAlwaysReturnsFalse(): void
    {
        $manager = new SyncCompatibleQueueManager();

        $this->assertFalse($manager->process());
        $this->assertFalse($manager->process('custom-queue'));
    }

    public function testGetStatsReturnsZeroedShape(): void
    {
        $manager = new SyncCompatibleQueueManager();

        $this->assertSame(
            ['pending' => 0, 'processing' => 0, 'failed' => 0],
            $manager->getStats()
        );
    }
}

/**
 * @internal test double
 */
class RecordingJob extends Job
{
    /** @var list<array<string, mixed>> */
    public static array $handled = [];
    public static bool $shouldFail = false;
    public static bool $failedWasCalled = false;

    public function handle(): void
    {
        if (self::$shouldFail) {
            throw new \RuntimeException('boom');
        }

        self::$handled[] = $this->getData();
    }

    public function failed(\Throwable $exception): void
    {
        self::$failedWasCalled = true;
    }
}
