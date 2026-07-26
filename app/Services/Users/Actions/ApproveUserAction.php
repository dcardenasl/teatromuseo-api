<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\Entities\UserEntity;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Support\ResolvesWebAppLinks;

class ApproveUserAction
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected EmailServiceInterface $emailService
    ) {
    }

    public function execute(int $id, ?SecurityContext $context = null, ?string $clientBaseUrl = null, ?string $locale = null): UserEntity
    {
        /** @var UserEntity|null $user */
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $status = (string) $user->status;
        if ($status === 'active') {
            throw new ConflictException(lang('Users.alreadyApproved'));
        }
        if ($status === 'invited') {
            throw new ConflictException(lang('Users.cannotApproveInvited'));
        }
        if ($status !== 'pending_approval') {
            throw new ConflictException(lang('Users.invalidApprovalState'));
        }

        $this->userRepository->withAuditAction('user_approved')->update($id, [
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $context?->user_id,
        ]);

        $emailLocale = $this->normalizeLocale($locale);

        $this->emailService->queueTemplate('account-approved', (string) $user->email, [
            'subject' => $this->subjectForLocale('Email.accountApproved.subject', $emailLocale),
            'display_name' => $user->getDisplayName(),
            'login_link' => $this->buildLoginUrl($clientBaseUrl),
            'locale' => $emailLocale,
        ]);

        /** @var UserEntity|null $approvedUser */
        $approvedUser = $this->userRepository->find($id);
        if ($approvedUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return $approvedUser;
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '') {
            $locale = (string) service('request')->getLocale();
        }

        foreach (config('App')->supportedLocales as $supportedLocale) {
            if (strtolower(trim((string) $supportedLocale)) === $locale) {
                return $locale;
            }
        }

        return (string) config('App')->defaultLocale;
    }

    private function subjectForLocale(string $line, string $locale): string
    {
        $previousLocale = null;

        try {
            $previousLocale = (string) service('request')->getLocale();
        } catch (\Throwable) {
            $previousLocale = null;
        }

        try {
            try {
                service('request')->setLocale($locale);
            } catch (\Throwable) {
                // no-op in CLI contexts without a request
            }

            try {
                service('language')->setLocale($locale);
            } catch (\Throwable) {
                // no-op if language service is unavailable
            }

            return lang($line);
        } finally {
            if ($previousLocale !== null && $previousLocale !== '') {
                try {
                    service('request')->setLocale($previousLocale);
                } catch (\Throwable) {
                    // no-op
                }
                try {
                    service('language')->setLocale($previousLocale);
                } catch (\Throwable) {
                    // no-op
                }
            }
        }
    }
}
