<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\Libraries\Iam\SelfPermissionService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * POST /api/v1/iam/self-permissions
 *
 * Allows a domain app (X-App-Key authenticated) to register its own permissions
 * in the hub without a superadmin JWT. Permission codes must be namespaced to
 * the app's code: an app with code "catalog" may only register "catalog.*".
 *
 * Idempotent — already-registered codes are counted as "existing" and skipped.
 *
 * Request body:
 *   {
 *     "permissions": [
 *       { "code": "catalog.read", "resource": "catalog", "action": "read", "description": "..." }
 *     ]
 *   }
 *
 * Response 200:
 *   { "status": "success", "data": { "created": N, "existing": N, "rejected": N, "errors": [...] } }
 */
class SelfPermissionsController extends Controller
{
    public function sync(): ResponseInterface
    {
        /** @var ApiRequest $request */
        $request = service('request');

        $appId = $request instanceof ApiRequest ? $request->getAppId() : null;
        if ($appId === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(ApiResponse::unauthorized('X-App-Key required'));
        }

        $raw  = $request->getJSON(assoc: true);
        $body = is_array($raw) ? $raw : [];

        $permissions = isset($body['permissions']) && is_array($body['permissions'])
            ? $body['permissions']
            : null;

        if ($permissions === null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(ApiResponse::validationError(['permissions' => 'Must be an array']));
        }

        /** @var list<array<string, string>> $permissions */
        $service = new SelfPermissionService(
            model(\App\Models\PermissionModel::class),
            model(\App\Models\ApplicationModel::class),
        );

        $result = $service->sync($appId, $permissions);

        return $this->response
            ->setStatusCode(200)
            ->setJSON(ApiResponse::success($result->toArray()));
    }
}
