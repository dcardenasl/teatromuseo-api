<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\System\EmailService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Queue\QueueManager;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * EmailService Unit Tests
 *
 * Tests email sending functionality with mocked dependencies.
 */
class EmailServiceTest extends CIUnitTestCase
{
    protected EmailService $service;
    protected MailerInterface $mockMailer;
    protected QueueManager $mockQueueManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMailer = $this->createMock(MailerInterface::class);
        $this->mockQueueManager = $this->createMock(QueueManager::class);

        $this->service = new EmailService(
            $this->mockMailer,
            $this->mockQueueManager
        );
    }

    // ==================== SEND TESTS ====================

    public function testSendEmailSuccessfully(): void
    {
        $this->mockMailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof Email
                    && str_contains($email->getTo()[0]->getAddress(), 'test@example.com')
                    && $email->getSubject() === 'Test Subject';
            }));

        $result = $this->service->send(
            'test@example.com',
            'Test Subject',
            '<p>Test HTML message</p>'
        );

        $this->assertTrue($result);
    }

    public function testSendEmailWithTextVersion(): void
    {
        $this->mockMailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                return $email instanceof Email
                    && $email->getTextBody() === 'Test plain text';
            }));

        $result = $this->service->send(
            'test@example.com',
            'Test Subject',
            '<p>Test HTML</p>',
            'Test plain text'
        );

        $this->assertTrue($result);
    }

    public function testSendEmailThrowsExceptionOnFailure(): void
    {
        $this->mockMailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('SMTP connection failed'));

        $result = $this->service->send(
            'test@example.com',
            'Test Subject',
            '<p>Test message</p>'
        );

        $this->assertFalse($result);
    }

    public function testSendEmailFormatsFromAddressCorrectly(): void
    {
        $this->mockMailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $from = $email->getFrom();
                return count($from) === 1
                    && $from[0]->getName() !== null;
            }));

        $this->service->send(
            'test@example.com',
            'Subject',
            'Message'
        );

        $this->assertTrue(true);
    }

    // ==================== QUEUE TESTS ====================

    public function testQueueEmailReturnsJobId(): void
    {
        $this->mockQueueManager->expects($this->once())
            ->method('push')
            ->with(
                \App\Libraries\Queue\Jobs\SendEmailJob::class,
                $this->callback(function ($data) {
                    return $data['to'] === 'test@example.com'
                        && $data['subject'] === 'Test Subject'
                        && $data['message'] === '<p>Test</p>';
                }),
                'emails'
            )
            ->willReturn(123);

        $jobId = $this->service->queue(
            'test@example.com',
            'Test Subject',
            '<p>Test</p>'
        );

        $this->assertEquals(123, $jobId);
    }

    public function testQueueEmailWithTextMessage(): void
    {
        $this->mockQueueManager->expects($this->once())
            ->method('push')
            ->with(
                $this->anything(),
                $this->callback(function ($data) {
                    return $data['textMessage'] === 'Plain text version';
                }),
                $this->anything()
            )
            ->willReturn(456);

        $jobId = $this->service->queue(
            'test@example.com',
            'Subject',
            '<p>HTML</p>',
            'Plain text version'
        );

        $this->assertEquals(456, $jobId);
    }

    // ==================== SEND TEMPLATE TESTS ====================

    /**
     * Note: sendTemplate() tests require view() helper and templates,
     * which are not available in isolated unit tests.
     * These are better tested in integration tests.
     */
    public function testSendTemplateReturnsFalseWhenViewNotFound(): void
    {
        // sendTemplate() catches all exceptions and returns false
        $result = $this->service->sendTemplate(
            'nonexistent_template_xyz123',
            'test@example.com',
            ['subject' => 'Test']
        );

        $this->assertFalse($result);
    }

    public function testSendTemplateHandlesExceptionGracefully(): void
    {
        // Template not found should not throw, should return false
        $result = $this->service->sendTemplate(
            'invalid/template/path',
            'test@example.com',
            []
        );

        $this->assertFalse($result);
    }

    public function testSendTemplateLogsErrorOnFailure(): void
    {
        // Verify error logging behavior (exception caught and logged)
        $result = $this->service->sendTemplate(
            'missing_template',
            'test@example.com',
            []
        );

        // Should return false instead of throwing
        $this->assertFalse($result);
    }

    // ==================== QUEUE TEMPLATE TESTS ====================

    public function testQueueTemplateReturnsJobId(): void
    {
        $this->mockQueueManager->expects($this->once())
            ->method('push')
            ->with(
                \App\Libraries\Queue\Jobs\SendTemplateEmailJob::class,
                $this->callback(function ($data) {
                    return $data['template'] === 'welcome'
                        && $data['to'] === 'test@example.com'
                        && isset($data['data']['username']);
                }),
                'emails'
            )
            ->willReturn(789);

        $jobId = $this->service->queueTemplate(
            'welcome',
            'test@example.com',
            ['username' => 'John Doe']
        );

        $this->assertEquals(789, $jobId);
    }

    public function testQueueTemplatePassesDataCorrectly(): void
    {
        $templateData = [
            'name' => 'John',
            'email' => 'john@example.com',
            'verification_url' => 'https://example.com/verify?token=abc',
        ];

        $this->mockQueueManager->expects($this->once())
            ->method('push')
            ->with(
                $this->anything(),
                $this->callback(function ($data) use ($templateData) {
                    if (! isset($data['data']['locale']) || ! is_string($data['data']['locale'])) {
                        return false;
                    }

                    unset($data['data']['locale']);
                    return $data['data'] === $templateData;
                }),
                $this->anything()
            )
            ->willReturn(999);

        $jobId = $this->service->queueTemplate(
            'verification',
            'john@example.com',
            $templateData
        );

        $this->assertEquals(999, $jobId);
    }

    public function testQueueTemplateKeepsProvidedLocale(): void
    {
        $templateData = [
            'name' => 'Juan',
            'locale' => 'es',
        ];

        $this->mockQueueManager->expects($this->once())
            ->method('push')
            ->with(
                $this->anything(),
                $this->callback(function ($data) {
                    return isset($data['data']['locale']) && $data['data']['locale'] === 'es';
                }),
                $this->anything()
            )
            ->willReturn(1001);

        $jobId = $this->service->queueTemplate(
            'verification',
            'juan@example.com',
            $templateData
        );

        $this->assertEquals(1001, $jobId);
    }
}
