<?php

declare(strict_types=1);

namespace App\Services\Auth;

use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;

class GoogleIdentityService implements \App\Interfaces\Auth\GoogleIdentityServiceInterface
{
    public function __construct(
        protected string $clientId = ''
    ) {
    }

    /**
     * Verify a Google ID token and return normalized claims.
     */
    public function verifyIdToken(string $id_token): \App\DTO\Response\Identity\GoogleIdentityResponseDTO
    {
        $token = trim($id_token);

        if ($token === '') {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleTokenRequired')]
            );
        }

        $clientId = trim($this->clientId);
        if ($clientId === '') {
            throw new ServiceUnavailableException(lang('Auth.googleClientNotConfigured'));
        }

        if (! class_exists('Google\\Client')) {
            throw new ServiceUnavailableException(lang('Auth.googleLibraryUnavailable'));
        }

        try {
            $googleClient = new \Google\Client(['client_id' => $clientId]);

            /** @var array<string, mixed>|false $payload */
            $payload = $googleClient->verifyIdToken($token);
        } catch (\Throwable $e) {
            log_message('warning', 'Google ID token verification failed: ' . $e->getMessage());
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        if (! is_array($payload)) {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        $issuer = (string) ($payload['iss'] ?? '');
        if (! in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        $audience = $payload['aud'] ?? null;
        $validAudience = is_string($audience)
            ? $audience === $clientId
            : (is_array($audience) && in_array($clientId, $audience, true));

        if (! $validAudience) {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= time()) {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['email' => lang('Auth.googleInvalidToken')]
            );
        }

        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $emailVerified) {
            throw new AuthenticationException(
                lang('Auth.googleEmailNotVerified'),
                ['email' => lang('Auth.googleEmailNotVerified')]
            );
        }

        return \App\DTO\Response\Identity\GoogleIdentityResponseDTO::fromArray([
            'provider' => 'google',
            'provider_id' => (string) ($payload['sub'] ?? ''),
            'email' => $email,
            'first_name' => isset($payload['given_name']) ? trim((string) $payload['given_name']) : null,
            'last_name' => isset($payload['family_name']) ? trim((string) $payload['family_name']) : null,
            'avatar_url' => isset($payload['picture']) ? trim((string) $payload['picture']) : null,
            'claims' => $payload,
        ]);
    }
}
