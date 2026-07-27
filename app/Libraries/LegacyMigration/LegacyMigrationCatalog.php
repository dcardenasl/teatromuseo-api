<?php

declare(strict_types=1);

namespace App\Libraries\LegacyMigration;

/**
 * Stable vocabulary shared by the migration orchestrator and its reports.
 * Target domains must not invent their own spelling for control-plane state.
 */
final class LegacyMigrationCatalog
{
    public const MODE_DRY_RUN = 'dry_run';
    public const MODE_APPLY = 'apply';

    public const RUN_RUNNING = 'running';
    public const RUN_COMPLETED = 'completed';
    public const RUN_FAILED = 'failed';
    public const RUN_CANCELLED = 'cancelled';

    public const MAP_MAPPED = 'mapped';
    public const MAP_PLANNED = 'planned';
    public const MAP_DUPLICATE = 'duplicate';
    public const MAP_QUARANTINED = 'quarantined';

    public const RESOLUTION_PENDING = 'pending';
    public const RESOLUTION_RESOLVED = 'resolved';
    public const RESOLUTION_SKIPPED = 'skipped';

    public const TARGET_CMS = 'cms-domain';
    public const TARGET_EVENT = 'event-domain';
    public const TARGET_CATALOG = 'catalog-domain';
    public const TARGET_HUB = 'api';

    /** @return list<string> */
    public static function runModes(): array
    {
        return [self::MODE_DRY_RUN, self::MODE_APPLY];
    }

    /** @return list<string> */
    public static function runStatuses(): array
    {
        return [self::RUN_RUNNING, self::RUN_COMPLETED, self::RUN_FAILED, self::RUN_CANCELLED];
    }

    /** @return list<string> */
    public static function mapStatuses(): array
    {
        return [self::MAP_MAPPED, self::MAP_PLANNED, self::MAP_DUPLICATE, self::MAP_QUARANTINED];
    }

    /** @return list<string> */
    public static function resolutions(): array
    {
        return [self::RESOLUTION_PENDING, self::RESOLUTION_RESOLVED, self::RESOLUTION_SKIPPED];
    }

    public static function isRunMode(string $value): bool
    {
        return in_array($value, self::runModes(), true);
    }

    public static function isRunStatus(string $value): bool
    {
        return in_array($value, self::runStatuses(), true);
    }

    public static function isMapStatus(string $value): bool
    {
        return in_array($value, self::mapStatuses(), true);
    }

    public static function isResolution(string $value): bool
    {
        return in_array($value, self::resolutions(), true);
    }
}
