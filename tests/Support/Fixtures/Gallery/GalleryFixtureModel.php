<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Gallery;

use CodeIgniter\Model;

/**
 * Fixture pivot model for GalleryService integration tests.
 *
 * Backed by the `gallery_test_pivots` table created on demand by the test
 * (see `GalleryServiceTest::setUp()`). Kept outside `app/Models/` so it
 * doesn't leak into production scaffolding or PHPStan paths.
 */
class GalleryFixtureModel extends Model
{
    protected $table          = 'gallery_test_pivots';
    protected $primaryKey     = 'id';
    protected $returnType     = 'object';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;

    protected $allowedFields = ['parent_id', 'file_id', 'sort_order', 'is_active'];

    protected $skipValidation = true;
}
