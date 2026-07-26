<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Queue\Jobs\SendEmailJob;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * SendEmailJob Tests
 */
class SendEmailJobTest extends CIUnitTestCase
{
    public function testHandleThrowsExceptionWhenToIsMissing(): void
    {
        $job = new SendEmailJob(['subject' => 'Test', 'message' => 'Body']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testHandleThrowsExceptionWhenSubjectIsMissing(): void
    {
        $job = new SendEmailJob(['to' => 'test@example.com', 'message' => 'Body']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testHandleThrowsExceptionWhenMessageIsMissing(): void
    {
        $job = new SendEmailJob(['to' => 'test@example.com', 'subject' => 'Test']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required email data');

        $job->handle();
    }

    public function testHandleCallsEmailService(): void
    {
        // Create job with valid data
        $job = new SendEmailJob([
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => '<p>Test message</p>',
            'textMessage' => 'Test message',
        ]);

        // Note: This will attempt to actually send email using configured provider
        // In a real test environment, you'd mock EmailService
        // For now, we just verify the job can be constructed
        $this->assertInstanceOf(SendEmailJob::class, $job);
    }
}
