<?php

declare(strict_types=1);

namespace Config;

/**
 * Audit configuration.
 *
 * Inherits the contract from `dcardenasl\Ci4ApiCore\Config\Audit` and
 * declares the entity-type aliases used by the starter's domain
 * (`'user' => 'users'`, etc.) plus app-specific actor table metadata.
 * Env-driven knobs (`AUDIT_ASYNC_ENABLED`, `AUDIT_QUEUE_NAME`,
 * `AUDIT_MAX_PAYLOAD_BYTES`) are applied in the constructor.
 */
class Audit extends \dcardenasl\Ci4ApiCore\Config\Audit
{
    /**
     * @var array<string, string>
     */
    public array $entityTypeAliases = [
        'user'    => 'users',
        'api-key' => 'api_keys',
        'file'    => 'files',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->asyncEnabled    = ENVIRONMENT !== 'testing' && (bool) env('AUDIT_ASYNC_ENABLED', true);
        $this->queueName       = (string) env('AUDIT_QUEUE_NAME', 'audit');
        $this->maxPayloadBytes = (int) env('AUDIT_MAX_PAYLOAD_BYTES', 60000);
    }
}
