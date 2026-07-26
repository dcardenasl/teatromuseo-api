<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\FileEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

/**
 * File Model
 *
 * Manages file metadata in the database
 */
class FileModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;
    protected $table = 'files';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = FileEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'original_name',
        'stored_name',
        'mime_type',
        'category',
        'size',
        'storage_driver',
        'path',
        'url',
        'alt_text',
        'caption',
        'credit',
        'width',
        'height',
        'duration_seconds',
        'page_count',
        'metadata',
        'variants',
        'uploaded_at',
        'deleted_by_user_id',
    ];

    // Timestamps
    protected $useTimestamps = false; // Using uploaded_at instead
    protected $dateFormat = 'datetime';

    /**
     * Validation rules
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'user_id' => 'required|integer',
        'original_name' => 'required|max_length[255]',
        'stored_name' => 'required|max_length[255]',
        'mime_type' => 'required|max_length[100]',
        'size' => 'required|integer',
        'storage_driver' => 'required|max_length[50]',
        'path' => 'required|max_length[500]',
    ];

    /**
     * Validation messages
     *
     * @var array<string, array<string, string>>
     */
    protected $validationMessages = [
        'user_id' => [
            'required' => 'InputValidation.common.userIdRequired',
            'integer' => 'InputValidation.common.userIdMustBeInteger',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Query capabilities
    /** @var array<int, string> */
    protected array $searchableFields = ['original_name', 'mime_type', 'alt_text', 'caption', 'credit'];
    /** @var array<int, string> */
    protected array $filterableFields = ['user_id', 'mime_type', 'category', 'size', 'uploaded_at', 'storage_driver', 'deleted_at'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'user_id', 'original_name', 'size', 'uploaded_at', 'mime_type', 'category', 'deleted_at'];

}
