<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ApiKeyEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

class ApiKeyModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table            = 'api_keys';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ApiKeyEntity::class;

    // No soft deletes — hard-delete API keys when revoked
    protected $useSoftDeletes   = false;

    protected $protectFields    = true;
    protected $allowedFields    = [
        'application_id',
        'name',
        'key_prefix',
        'key_hash',
        'is_active',
        'rate_limit_requests',
        'rate_limit_window',
        'user_rate_limit',
        'ip_rate_limit',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * @var array<string, array<string, array<string, string>|string>|string>
     */
    protected $validationRules = [
        'name' => [
            'rules'  => 'required|string|max_length[100]',
            'errors' => [
                'required'   => 'InputValidation.apiKey.nameRequired',
                'max_length' => 'InputValidation.apiKey.nameMaxLength',
            ],
        ],
        'key_prefix' => [
            'rules'  => 'required|string|max_length[12]',
            'errors' => [
                'required'   => 'InputValidation.apiKey.keyPrefixRequired',
                'max_length' => 'InputValidation.apiKey.keyPrefixMaxLength',
            ],
        ],
        'key_hash' => [
            'rules'  => 'required|string|max_length[64]',
            'errors' => [
                'required'   => 'InputValidation.apiKey.keyHashRequired',
                'max_length' => 'InputValidation.apiKey.keyHashMaxLength',
            ],
        ],
        'rate_limit_requests' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'InputValidation.apiKey.rateLimitRequestsInteger',
                'greater_than' => 'InputValidation.apiKey.rateLimitRequestsGreaterThan',
            ],
        ],
        'rate_limit_window' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'InputValidation.apiKey.rateLimitWindowInteger',
                'greater_than' => 'InputValidation.apiKey.rateLimitWindowGreaterThan',
            ],
        ],
        'user_rate_limit' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'InputValidation.apiKey.userRateLimitInteger',
                'greater_than' => 'InputValidation.apiKey.userRateLimitGreaterThan',
            ],
        ],
        'ip_rate_limit' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'InputValidation.apiKey.ipRateLimitInteger',
                'greater_than' => 'InputValidation.apiKey.ipRateLimitGreaterThan',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Search and filter configuration
    /** @var array<int, string> */
    protected array $searchableFields  = ['name', 'key_prefix'];
    /** @var array<int, string> */
    protected array $filterableFields  = ['name', 'is_active', 'created_at'];
    /** @var array<int, string> */
    protected array $sortableFields    = ['id', 'name', 'is_active', 'created_at'];

    /**
     * Find an API key entity by its raw SHA-256 hash.
     *
     * @param string $hash SHA-256 hash of the raw key
     * @return ApiKeyEntity|null
     */
    public function findByHash(string $hash): ?ApiKeyEntity
    {
        /** @var ApiKeyEntity|null */
        return $this->where('key_hash', $hash)->first();
    }

}
