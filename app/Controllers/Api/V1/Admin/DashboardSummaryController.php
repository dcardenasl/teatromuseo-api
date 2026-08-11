<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\DTO\Response\Admin\DashboardSummaryResponseDTO;
use App\Services\Admin\DashboardSummaryService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;

final class DashboardSummaryController extends ApiController
{
    private DashboardSummaryService $dashboardSummaryService;

    protected function resolveDefaultService(): object
    {
        $this->dashboardSummaryService = Services::dashboardSummaryService();

        return $this->dashboardSummaryService;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest(
            function (mixed $payload, SecurityContext $context): DashboardSummaryResponseDTO {
                return $this->dashboardSummaryService->read($context);
            }
        );
    }
}
