<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\Interfaces\Auth\TokenIntrospectionServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

/**
 * Token Introspection Controller
 *
 * Exposes JWT validation as an HTTP endpoint so domain apps can verify
 * user tokens without sharing the JWT secret. Caller is authenticated
 * via X-App-Key (appKeyRequired filter), which also identifies which
 * application the response scope must be resolved against.
 *
 * The controller re-reads the X-App-Key header (instead of trusting an
 * `appKeyId` previously stamped on the request by the filter) so it stays
 * decoupled from the concrete request subclass — see ServiceTokenController
 * for the same pattern. By the time this method runs, the filter has
 * already validated the header, so the lookup below is guaranteed to hit.
 */
class IntrospectController extends ApiController
{
    protected TokenIntrospectionServiceInterface $introspectionService;

    protected function resolveDefaultService(): object
    {
        $this->introspectionService = Services::tokenIntrospectionService();

        return $this->introspectionService;
    }

    public function introspect(): ResponseInterface
    {
        return $this->handleRequest(
            function (IntrospectRequestDTO $dto) {
                return $this->introspectionService->introspect(
                    $dto,
                    $this->callerApplicationId()
                );
            },
            IntrospectRequestDTO::class
        );
    }

    /**
     * Resolve the caller's application id. Uses the appKeyId already stamped
     * by AppKeyRequiredFilter (PK lookup) when available; falls back to the
     * raw header + hash for non-ApiRequest contexts such as unit tests.
     */
    private function callerApplicationId(): ?int
    {
        $appKeyId = $this->request instanceof ApiRequest
            ? $this->request->getAppKeyId()
            : null;

        if ($appKeyId !== null) {
            $apiKey = Services::apiKeyRepository()->find($appKeyId);
        } else {
            $rawKey = (string) $this->request->getHeaderLine('X-App-Key');
            if ($rawKey === '') {
                return null;
            }
            $apiKey = Services::apiKeyRepository()->findByHash(
                Services::apiKeyMaterialService()->hash($rawKey)
            );
        }

        if ($apiKey === null) {
            return null;
        }

        $applicationId = $apiKey->application_id ?? null;
        return $applicationId !== null ? (int) $applicationId : null;
    }
}
