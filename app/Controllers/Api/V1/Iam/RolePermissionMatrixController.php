<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

class RolePermissionMatrixController extends Controller
{
    public function index(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(200)
            ->setJSON(ApiResponse::success(Services::rolePermissionMatrixService()->matrix()->toArray()));
    }
}
