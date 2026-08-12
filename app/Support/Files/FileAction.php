<?php

declare(strict_types=1);

namespace App\Support\Files;

/**
 * Actions that can be performed against a persisted file.
 *
 * Keeping the action as a closed value set prevents callers from passing an
 * independent ownership-bypass flag that can accidentally be reused by a
 * mutating operation.
 */
enum FileAction: string
{
    case VIEW = 'view';
    case DOWNLOAD = 'download';
    case VIEW_USAGES = 'view_usages';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case FORCE_DELETE = 'force_delete';
    case REPLACE = 'replace';
    case UPDATE_METADATA = 'update_metadata';
    case REGENERATE_VARIANTS = 'regenerate_variants';

    public function isRead(): bool
    {
        return match ($this) {
            self::VIEW, self::DOWNLOAD, self::VIEW_USAGES => true,
            default => false,
        };
    }

    public function auditSuffix(): string
    {
        return match ($this) {
            self::VIEW => 'access',
            self::DOWNLOAD => 'download',
            self::VIEW_USAGES => 'usages',
            self::DELETE => 'delete',
            self::RESTORE => 'restore',
            self::FORCE_DELETE => 'force_delete',
            self::REPLACE => 'replace',
            self::UPDATE_METADATA => 'update_metadata',
            self::REGENERATE_VARIANTS => 'regenerate_variants',
        };
    }
}
