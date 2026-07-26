<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Entities\UserEntity;
use App\Interfaces\System\EmailServiceInterface;
use App\Models\PasswordResetModel;
use dcardenasl\Ci4ApiCore\Security\Token;
use dcardenasl\Ci4ApiCore\Support\ResolvesWebAppLinks;

/**
 * User Invitation Service
 *
 * Handles the orchestration of user invitations and account activation flows.
 */
class UserInvitationService
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected PasswordResetModel $passwordResetModel,
        protected EmailServiceInterface $emailService
    ) {
    }

    /**
     * Send invitation email to a newly created user.
     */
    public function sendInvitation(UserEntity $user, ?string $clientBaseUrl = null, ?string $locale = null): void
    {
        $email = (string) ($user->email ?? '');
        if ($email === '') {
            return;
        }

        $token = Token::generate();
        $emailLocale = $this->normalizeLocale($locale);

        // Standardize the password reset invitation flow
        $this->passwordResetModel->where('email', $email)->delete();
        $this->passwordResetModel->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $resetLink = $this->buildResetPasswordUrl($token, $email, $clientBaseUrl);
        $displayName = (string) $user->getDisplayName();

        $this->emailService->queueTemplate('invitation', $email, [
            'subject' => $this->subjectForLocale('Email.invitation.subject', $emailLocale),
            'display_name' => $displayName,
            'reset_link' => $resetLink,
            'expires_in' => '60 minutes',
            'locale' => $emailLocale,
        ]);
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
