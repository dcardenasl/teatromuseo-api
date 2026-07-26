<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail to avoid growing direct Model coupling in service layer.
 *
 * Services must not import Models directly (`use App\Models\...`).
 * The six whitelisted files below are justified exceptions (auth internals,
 * token lifecycle, system metrics) that pre-date the repository layer.
 *
 * For cross-entity queries in domain services, inject a second repository
 * via the constructor and use `findBy()`:
 *
 *   public function __construct(
 *       protected SubscriberRepository $repository,
 *       protected ProjectRepository    $projectRepository,
 *   ) {}
 *
 *   // Then: $this->projectRepository->findBy('project_key', $key)
 *
 * This keeps the service PHPStan-clean and satisfies this guardrail without
 * resorting to inline FQCNs like `model(\App\Models\ProjectModel::class)`.
 */
class ServiceModelDependencyConventionsTest extends CIUnitTestCase
{
    public function testServicesUsingModelsAreExplicitlyWhitelisted(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $serviceDir = $root . DIRECTORY_SEPARATOR . 'app/Services';

        $allowed = [
            'app/Services/Auth/PasswordResetService.php',
            'app/Services/Auth/ServiceTokenService.php',
            'app/Services/Auth/UserInvitationService.php',
            'app/Services/System/MetricsService.php',
            'app/Services/Tokens/RefreshTokenService.php',
            'app/Services/Tokens/TokenRevocationService.php',
        ];
        sort($allowed);

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($serviceDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            if (preg_match('/^use\s+App\\\\Models\\\\/m', $source) !== 1) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));
            $found[] = $relative;
        }

        sort($found);
        $this->assertSame(
            $allowed,
            $found,
            "Services with direct Model imports changed.\n" .
            'Prefer repositories/interfaces and update this whitelist only for justified exceptions.'
        );
    }
}
