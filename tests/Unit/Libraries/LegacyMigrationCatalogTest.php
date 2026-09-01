<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyMigrationCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class LegacyMigrationCatalogTest extends TestCase
{
    public function testVocabularyContainsAllTargetDomainSystems(): void
    {
        $this->assertSame(
            ['cms-domain', 'event-domain', 'catalog-domain', 'api'],
            [
                LegacyMigrationCatalog::TARGET_CMS,
                LegacyMigrationCatalog::TARGET_EVENT,
                LegacyMigrationCatalog::TARGET_CATALOG,
                LegacyMigrationCatalog::TARGET_HUB,
            ]
        );
    }

    public function testOnlyKnownControlPlaneValuesAreAccepted(): void
    {
        $this->assertTrue(LegacyMigrationCatalog::isRunMode('dry_run'));
        $this->assertTrue(LegacyMigrationCatalog::isRunStatus('completed'));
        $this->assertTrue(LegacyMigrationCatalog::isMapStatus('quarantined'));
        $this->assertTrue(LegacyMigrationCatalog::isResolution('resolved'));
        $this->assertFalse(LegacyMigrationCatalog::isRunMode('execute_now'));
        $this->assertFalse(LegacyMigrationCatalog::isResolution('closed'));
    }
}
