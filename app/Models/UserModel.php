<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\UserEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

class UserModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    /**
     * @var string
     */
    protected $table            = 'users';

    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserEntity::class;
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    /**
     * @var list<string>
     */
    protected $allowedFields    = [
        'email',
        'first_name',
        'last_name',
        'password',
        'status',
        'approved_at',
        'approved_by',
        'invited_at',
        'invited_by',
        'oauth_provider',
        'oauth_provider_id',
        'avatar_url',
        'email_verification_token',
        'verification_token_expires',
        'email_verified_at',
        'deleted_at',
    ];

    protected $useTimestamps      = true;
    protected $dateFormat         = 'datetime';
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';

    /**
     * Validation rules (data integrity)
     *
     * @var array<string, array<string, array<string, string>|string>|string>
     */
    protected $validationRules = [
        'id'    => 'permit_empty|is_natural_no_zero',
        'email' => [
            'rules'  => 'permit_empty|valid_email_idn|max_length[255]|is_unique[users.email,id,{id}]',
            'errors' => [
                'valid_email_idn' => 'InputValidation.common.emailInvalid',
                'max_length' => 'InputValidation.common.emailMaxLength',
                'is_unique' => 'InputValidation.common.emailAlreadyRegistered',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /** @var array<int, string> */
    protected array $searchableFields = ['email', 'first_name', 'last_name'];
    /** @var array<int, string> */
    protected array $filterableFields = ['status', 'email', 'created_at', 'id', 'first_name', 'last_name'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'email', 'created_at', 'status', 'first_name', 'last_name'];

    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

}
