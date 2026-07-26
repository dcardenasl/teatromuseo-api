<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Gallery;

use App\Repositories\Common\PivotRepository;

/**
 * Fixture pivot repository wired against the test-only
 * `gallery_test_pivots` table. Demonstrates the canonical pattern:
 * extend `PivotRepository`, return the FK column name in `getParentKey()`.
 */
class GalleryFixtureRepository extends PivotRepository
{
    public function getParentKey(): string
    {
        return 'parent_id';
    }
}
