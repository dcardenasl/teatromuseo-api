<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\DTO\Response\Admin\DashboardSummaryResponseDTO;
use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

final readonly class DashboardSummaryService
{
    public function __construct(
        private DashboardSummaryRepositoryInterface $repository,
    ) {
    }

    /**
     * Return only sections the current actor is allowed to see.
     *
     * @return DashboardSummaryResponseDTO
     */
    public function read(SecurityContext $context): DashboardSummaryResponseDTO
    {
        $permissions = $context->permissions;
        $allowedPermissions = ['users.read', 'files.read', 'metrics.read'];
        if (array_intersect($allowedPermissions, $permissions) === []) {
            return DashboardSummaryResponseDTO::fromArray([
                'version' => 1,
                'generated_at' => date(DATE_ATOM),
                'sections' => [],
            ]);
        }

        $source = $this->repository->read();
        $sections = [];

        if (in_array('users.read', $permissions, true)) {
            $sections['users'] = ['total' => (int) ($source['users_total'] ?? 0)];
        }

        if (in_array('files.read', $permissions, true)) {
            $sections['files'] = [
                'total' => (int) ($source['files_total'] ?? 0),
                'recent' => is_array($source['recent_files'] ?? null) ? $source['recent_files'] : [],
            ];
        }

        if (in_array('metrics.read', $permissions, true)) {
            $sections['metrics'] = is_array($source['metrics'] ?? null) ? $source['metrics'] : [];
        }

        return DashboardSummaryResponseDTO::fromArray([
            'version' => 1,
            'generated_at' => date(DATE_ATOM),
            'sections' => $sections,
        ]);
    }
}
