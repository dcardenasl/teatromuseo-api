<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures\Translations;

use CodeIgniter\Model;

/**
 * Fixture model for HandlesTranslations integration tests.
 *
 * Backed by the `translation_test_parents` table created on demand by the
 * test (see `HandlesTranslationsTest::setUp()`). Kept outside `app/Models/`
 * so it doesn't leak into production scaffolding or PHPStan paths.
 */
class TranslationFixtureModel extends Model
{
    protected $table          = 'translation_test_parents';
    protected $primaryKey     = 'id';
    protected $returnType     = 'object';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = ['name'];

    protected $skipValidation = true;
}
