<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Queue\Jobs\SendTemplateEmailJob;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * SendTemplateEmailJob Tests
 */
class SendTemplateEmailJobTest extends CIUnitTestCase
{
    public function testHandleThrowsExceptionWhenToIsMissing(): void
    {
        $job = new SendTemplateEmailJob([
            'template' => 'welcome',
            'data' => ['name' => 'John'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testHandleThrowsExceptionWhenTemplateIsMissing(): void
    {
        $job = new SendTemplateEmailJob([
            'to' => 'test@example.com',
            'data' => ['name' => 'John'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testHandleThrowsExceptionWhenDataIsMissing(): void
    {
        $job = new SendTemplateEmailJob([
            'to' => 'test@example.com',
            'template' => 'welcome',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testJobCanBeConstructedWithValidData(): void
    {
        $job = new SendTemplateEmailJob([
            'to' => 'test@example.com',
            'template' => 'welcome',
            'data' => ['name' => 'John', 'subject' => 'Welcome'],
        ]);

        $this->assertInstanceOf(SendTemplateEmailJob::class, $job);
    }
}
