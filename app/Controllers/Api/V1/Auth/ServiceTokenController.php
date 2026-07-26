<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Interfaces\Auth\ServiceTokenServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;

/**
 * Service Token Controller
 *
 * Issues OAuth client_credentials-style JWTs to domain applications. The
 * caller authenticates with `X-App-Key` (validated by AppKeyRequiredFilter).
 * Uses the appKeyId already stamped by the filter to avoid re-hashing the
 * raw key; falls back to the raw header for non-ApiRequest test contexts.
 */
class ServiceTokenController extends ApiController
{
    protected ServiceTokenServiceInterface $serviceTokenService;

    protected function resolveDefaultService(): object
    {
        $this->serviceTokenService = Services::serviceTokenService();

        return $this->serviceTokenService;
    }

    public function issue(): ResponseInterface
    {
        return $this->handleRequest(function (): mixed {
            $appKeyId = $this->request instanceof ApiRequest
                ? $this->request->getAppKeyId()
                : null;

            if ($appKeyId !== null) {
                return $this->serviceTokenService->issueByKeyId($appKeyId);
            }

            return $this->serviceTokenService->issue(
                (string) $this->request->getHeaderLine('X-App-Key')
            );
        });
    }
}
