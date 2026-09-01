<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\DTO\Request\Iam\SelfPermissionsRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

/**
 * POST /api/v1/iam/self-permissions
 *
 * Allows a domain app (X-App-Key authenticated) to register its own permissions
 * in the hub without a superadmin JWT. Permission codes must be namespaced to
 * the app's code: an app with code "catalog" may only register "catalog.*".
 *
 * Idempotent — already-registered codes are counted as "existing" and skipped.
 */
class SelfPermissionsController extends ApiController
{
    protected function resolveDefaultService(): object
    {
        return Services::selfPermissionService();
    }

    public function sync(): ResponseInterface
    {
        return $this->handleRequest(
            function (SelfPermissionsRequestDTO $dto, SecurityContext $context): array {
                /** @var ApiRequest $request */
                $request = service('request');
                $appId = $request instanceof ApiRequest ? $request->getAppId() : null;

                if ($appId === null) {
                    // AppKeyRequiredFilter should prevent reaching here;
                    // guard defensively without ResponseInterface leak.
                    return [];
                }

                /** @var list<array<string, string>> $permissions */
                $permissions = is_array($dto->permissions) ? array_values($dto->permissions) : [];
                $service = Services::selfPermissionService();
                $result = $service->sync($appId, $permissions);

                return $result->toArray();
            },
            SelfPermissionsRequestDTO::class
        );
    }
}
