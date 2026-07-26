<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Internal;

use App\DTO\Request\Internal\InternalEmailQueueRequestDTO;
use App\Interfaces\System\EmailServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Internal M2M endpoint for queuing emails.
 *
 * Called exclusively by trusted Domain apps via X-App-Key authentication.
 * Delegates to Hub's EmailService::queue() so the Hub remains the single
 * email sender — no Symfony Mailer needed in Domain apps.
 */
class InternalEmailController extends ApiController
{
    protected function resolveDefaultService(): object
    {
        return Services::emailService();
    }

    public function queue(): ResponseInterface
    {
        return $this->handleRequest(
            function (InternalEmailQueueRequestDTO $dto, SecurityContext $context): mixed {
                /** @var EmailServiceInterface $emailService */
                $emailService = Services::emailService();
                $jobId = $emailService->queue($dto->to, $dto->subject, $dto->message, $dto->text_message);
                return ['job_id' => $jobId];
            },
            InternalEmailQueueRequestDTO::class
        );
    }
}
