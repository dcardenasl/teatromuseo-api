<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Interfaces\Files\FileServiceInterface;
use App\Services\Files\NullVirusScannerService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email;
use Config\Services;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use Symfony\Component\Mailer\MailerInterface;

final class ServicesWiringTest extends CIUnitTestCase
{
    public function testFileResponseMapperFactoryReturnsResponseMapper(): void
    {
        $mapper = Services::fileResponseMapper(false);

        $this->assertInstanceOf(ResponseMapperInterface::class, $mapper);
    }

    public function testFileServiceFactoryResolvesDependencies(): void
    {
        $service = Services::fileService(false);

        $this->assertInstanceOf(FileServiceInterface::class, $service);
    }

    public function testVirusScannerFactoryUsesExplicitNullScanner(): void
    {
        $scanner = Services::virusScannerService(false);

        $this->assertInstanceOf(NullVirusScannerService::class, $scanner);
    }

    public function testBuildMailerFromConfigReturnsMailerForSmtpProvider(): void
    {
        $emailConfig = new Email();
        $emailConfig->provider = 'smtp';
        $emailConfig->SMTPHost = 'localhost';
        $emailConfig->SMTPPort = 1025;
        $emailConfig->SMTPUser = '';
        $emailConfig->SMTPPass = '';
        $emailConfig->SMTPCrypto = '';

        $method = new \ReflectionMethod(Services::class, 'buildMailerFromConfig');
        $method->setAccessible(true);
        $mailer = $method->invoke(null, $emailConfig);

        $this->assertInstanceOf(MailerInterface::class, $mailer);
    }

    public function testBuildMailerFromConfigReturnsNullWhenProviderDisabled(): void
    {
        $emailConfig = new Email();
        $emailConfig->provider = 'none';

        $method = new \ReflectionMethod(Services::class, 'buildMailerFromConfig');
        $method->setAccessible(true);
        $mailer = $method->invoke(null, $emailConfig);

        $this->assertNull($mailer);
    }
}
