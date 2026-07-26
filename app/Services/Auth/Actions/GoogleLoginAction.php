<?php

declare(strict_types=1);

namespace App\Services\Auth\Actions;

use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\Response\Auth\PendingRegistrationResponseDTO;
use App\Entities\UserEntity;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Support\GoogleAuthHandler;
use App\Services\Auth\Support\SessionManager;
use App\Services\Users\UserAccountGuard;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

class GoogleLoginAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected GoogleIdentityServiceInterface $googleIdentityService,
        protected GoogleAuthHandler $googleHandler,
        protected SessionManager $sessionManager,
        protected UserAccountGuard $userAccessPolicy,
        protected AuditServiceInterface $auditService,
        protected EmailServiceInterface $emailService
    ) {
    }

    public function execute(GoogleLoginRequestDTO $request, ?SecurityContext $context = null): OperationResult
    {
        try {
            $identity = $this->googleIdentityService->verifyIdToken($request->id_token);
        } catch (\Throwable $e) {
            $this->auditService->log(
                'google_login_failure',
                'users',
                null,
                [],
                ['reason' => 'invalid_google_token'],
                $context,
                'failure',
                'warning'
            );
            throw $e;
        }
        $context ??= SecurityContext::anonymous();
        $email = strtolower($identity->email);

        /** @var UserEntity|null $user */
        $user = $this->userRepository->findByEmailWithDeleted($email);

        if (!$user) {
            $pending = $this->googleHandler->createPendingUser($identity->toArray());
            $this->sendPendingApprovalEmail($pending);

            $userContext = new SecurityContext((int) $pending->id, $context->metadata);
            $this->auditService->log(
                'google_registration_pending',
                'users',
                (int) $pending->id,
                [],
                ['email' => $email, 'provider' => 'google'],
                $userContext
            );

            return OperationResult::accepted(
                ['user' => PendingRegistrationResponseDTO::fromUser($pending)->toArray()],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        if ($user->deleted_at !== null) {
            $user = $this->googleHandler->reactivateDeletedUser($user, $identity->toArray());
            $this->sendPendingApprovalEmail($user);

            return OperationResult::accepted(
                ['user' => PendingRegistrationResponseDTO::fromUser($user)->toArray()],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        $updateData = [];

        if (($user->status ?? null) === 'active') {

            if (($user->oauth_provider ?? null) === null) {
                $updateData['oauth_provider'] = 'google';
            }
            if (($user->oauth_provider ?? null) === 'google' && empty($user->oauth_provider_id)) {
                $updateData['oauth_provider_id'] = $identity->provider_id;
            }
            if ($user->email_verified_at === null) {
                $updateData['email_verified_at'] = date('Y-m-d H:i:s');
            }
            if (($user->invited_at ?? null) !== null) {
                $updateData['invited_at'] = null;
                $updateData['invited_by'] = null;
            }

            if ($updateData !== []) {
                $this->userRepository->withAuditAction('google_login_success')->update((int) $user->id, $updateData);
                /** @var UserEntity|null $refreshed */
                $refreshed = $this->userRepository->find((int) $user->id);
                if ($refreshed === null) {
                    throw new \RuntimeException(lang('Auth.googleUserMissing'));
                }
                $user = $refreshed;
            }
        }

        $this->userAccessPolicy->assertCanAuthenticate($user);
        $this->googleHandler->syncProfileIfEmpty((int) $user->id, $identity->toArray());

        /** @var UserEntity|null $freshUser */
        $freshUser = $this->userRepository->find((int) $user->id);
        if ($freshUser === null) {
            throw new \RuntimeException(lang('Auth.googleUserMissing'));
        }

        if ($updateData === []) {
            $this->auditService->log(
                'google_login_success',
                'users',
                (int) $freshUser->id,
                [],
                ['email' => $email, 'provider' => 'google'],
                $context
            );
        }

        return OperationResult::success(
            $this->sessionManager->generateSessionResponse($freshUser)
        );
    }

    private function sendPendingApprovalEmail(object $user): void
    {
        try {
            $this->emailService->queueTemplate('pending-approval-google', (string) $user->email, [
                'subject' => lang('Email.pendingApprovalGoogle.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : (string) $user->email,
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Failed to queue email: ' . $exception->getMessage());
        }
    }
}
