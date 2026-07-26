<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every business model under app/Models must extend BaseAuditableModel so audit
 * logging is automatic. The scan is dynamic: a newly scaffolded model is covered
 * the moment it lands, without editing this test.
 */
class AuditableModelConventionsTest extends CIUnitTestCase
{
    /**
     * Models that legitimately do NOT carry an audit trail — system, log, token,
     * and join/pivot tables. A model NOT on this list must extend
     * BaseAuditableModel, so a scaffolded business entity that bypasses it fails
     * here. Adding a new entry is a conscious, reviewable decision.
     *
     * @var list<string>
     */
    private const NON_AUDITABLE = [
        'AuditLogModel',
        'MetricModel',
        'PasswordResetModel',
        'RefreshTokenModel',
        'RequestLogModel',
        'RolePermissionModel',
        'TokenBlacklistModel',
        'TranslationModel',
        'UserRoleModel',
    ];

    public function testAuditableModelsExtendSharedBaseAuditableModel(): void
    {
        $dir        = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR) . '/app/Models';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), 'Model.php')) {
                continue;
            }

            $name = $file->getBasename('.php');
            if (in_array($name, self::NON_AUDITABLE, true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! is_string($source) || $source === '') {
                $violations[] = "{$name}: could not read source";
                continue;
            }

            $extendsBase = str_contains($source, 'extends BaseAuditableModel')
                || str_contains($source, 'extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel');
            if (! $extendsBase) {
                $violations[] = "{$name}: must extend BaseAuditableModel (or be added to NON_AUDITABLE with rationale)";
            }

            if (str_contains($source, 'use dcardenasl\Ci4ApiCore\Models\Auditable;')) {
                $violations[] = "{$name}: should not import Auditable directly";
            }
        }

        $this->assertSame([], $violations, "Auditable model convention violations:\n- " . implode("\n- ", $violations));
    }
}
