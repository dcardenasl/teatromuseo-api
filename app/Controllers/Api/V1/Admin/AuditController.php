<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\DTO\Request\Audit\AuditByEntityRequestDTO;
use App\DTO\Request\Audit\AuditIndexRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * Modernized Audit Controller
 *
 * Provides administrative access to the system audit trail.
 */
class AuditController extends ApiController
{
    protected AuditServiceInterface $auditService;

    protected function resolveDefaultService(): object
    {
        $this->auditService = Services::auditService();

        return $this->auditService;
    }

    /**
     * List all audit logs
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', AuditIndexRequestDTO::class);
    }

    /**
     * Get a single audit log detail
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->auditService->show($id, $context));
    }


    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(string $type, int $id): ResponseInterface
    {
        return $this->handleRequest(
            'byEntity',
            AuditByEntityRequestDTO::class,
            ['entity_type' => $type, 'entity_id' => $id]
        );
    }
}
