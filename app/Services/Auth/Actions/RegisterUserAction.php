<?php

declare(strict_types=1);

namespace App\Services\Auth\Actions;

use App\DTO\Request\Auth\RegisterRequestDTO;
use App\Interfaces\Auth\VerificationServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\UserRoleAssignmentService;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Support\ResolvesWebAppLinks;

class RegisterUserAction
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected VerificationServiceInterface $verificationService,
        protected EmailServiceInterface $emailService,
        protected UserRoleAssignmentService $userRoleAssignmentService
    ) {
    }

    public function execute(RegisterRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        $requiresVerification = Hasher::isEmailVerificationRequired();
        $status = $requiresVerification ? 'pending_approval' : 'active';
        $now = date('Y-m-d H:i:s');
        $locale = $request->locale;
        $emailLocale = $this->normalizeLocale($locale);

        $userId = $this->userRepository->insert([
            'email'      => $request->email,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'password'   => password_hash($request->password, PASSWORD_BCRYPT),
            'role'       => 'user',
            'status'     => $status,
            'approved_at' => $requiresVerification ? null : $now,
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userRepository->errors());
        }

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['user' => lang('Api.resourceNotFound')]);
        }

        $this->userRoleAssignmentService->assignRoleByCode((int) $userId, 'user');

        if ($requiresVerification) {
            try {
                $this->verificationService->sendVerificationEmail((int) $userId, $context, $locale);
            } catch (\Throwable $exception) {
                log_message('error', 'Failed to send verification email: ' . $exception->getMessage());
            }
        } else {
            try {
                $this->emailService->queueTemplate('account-approved', (string) $user->email, [
                    'subject' => $this->subjectForLocale('Email.accountApproved.subject', $emailLocale),
                    'display_name' => $user->getDisplayName(),
                    'login_link' => $this->buildLoginUrl(),
                    'locale' => $emailLocale,
                ]);
            } catch (\Throwable $exception) {
                log_message('error', 'Failed to queue approval email: ' . $exception->getMessage());
            }
        }

        return $user;
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '') {
            $locale = (string) service('request')->getLocale();
        }

        $supported = config('App')->supportedLocales ?? [];
        foreach ($supported as $supportedLocale) {
            if (strtolower(trim((string) $supportedLocale)) === $locale) {
                return $locale;
            }
        }

        return config('App')->defaultLocale ?? 'en';
    }

    private function subjectForLocale(string $line, string $locale): string
    {
        $previous = $this->currentLocale();
        $this->applyLocale($locale);

        try {
            return lang($line);
        } finally {
            if ($previous !== null) {
                $this->applyLocale($previous);
            }
        }
    }

    private function currentLocale(): ?string
    {
        try {
            return (string) service('request')->getLocale();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyLocale(string $locale): void
    {
        try {
            service('request')->setLocale($locale);
        } catch (\Throwable) {
        }

        try {
            service('language')->setLocale($locale);
        } catch (\Throwable) {
        }
    }
}
