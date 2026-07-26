<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Translations;

use App\Traits\HandlesTranslations;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * Fixture service for HandlesTranslations integration tests.
 *
 * The whole point of having a real service here (rather than mocking
 * BaseCrudService) is that the trait's `afterStore` / `afterUpdate` /
 * `afterDelete` hooks must run inside the same transaction the parent
 * opens via `wrapInTransaction()`. That can only be exercised end-to-end.
 */
class TranslationFixtureService extends BaseCrudService
{
    use HandlesTranslations;

    protected function getTranslatableType(): string
    {
        return 'translation_test_parents';
    }
}
