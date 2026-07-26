<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\DTO\Request\Iam\ApplicationIndexRequestDTO;
use App\Interfaces\Iam\ApplicationServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

class ApplicationController extends ApiController
{
    protected ApplicationServiceInterface $applicationService;

    protected function resolveDefaultService(): object
    {
        $this->applicationService = Services::applicationService();

        return $this->applicationService;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ApplicationIndexRequestDTO::class);
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->applicationService->show($id, $context));
    }
}
