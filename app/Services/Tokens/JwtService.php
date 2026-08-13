<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * JWT Service
 *
 * Handles creation and validation of JSON Web Tokens.
 * Focused on high-level security and strict typing.
 */
readonly class JwtService implements \App\Interfaces\Tokens\JwtServiceInterface
{
    private string $algorithm;

    public function __construct(
        private string $secretKey,
        private int $expirationTime = 3600,
        private string $issuer = ''
    ) {
        if (! $issuer) {
            throw new \RuntimeException(lang('Tokens.issuerRequired'));
        }
        if (strlen($this->secretKey) < 32) {
            throw new RuntimeException(lang('Api.jwtSecretTooShort'));
        }
        $this->algorithm = 'HS256';
    }

    /**
     * Generate a JWT token with JTI (unique identifier)
     *
     * @param list<string> $permissions Effective permission codes; encoded as the `scope` claim.
     */
    public function encode(int $userId, $permissions = [], int $tokenVersion = 0): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        // JTI is essential for token revocation support
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iss'   => $this->issuer,
            'iat'   => $issuedAt,
            'nbf'   => $issuedAt,
            'exp'   => $expirationTime,
            'jti'   => $jti,
            'uid'   => $userId,
            'scope' => array_values($permissions),
            'token_version' => max(0, $tokenVersion),
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Generate a service (machine-to-machine) JWT.
     *
     * @param list<string> $permissions Effective permission codes for the application
     */
    public function encodeServiceToken(string $sub, $permissions, int $ttl): string
    {
        $issuedAt       = time();
        $expirationTime = $issuedAt + $ttl;
        $jti            = bin2hex(random_bytes(16));

        $payload = [
            'iss'   => $this->issuer,
            'iat'   => $issuedAt,
            'nbf'   => $issuedAt,
            'exp'   => $expirationTime,
            'jti'   => $jti,
            'sub'   => $sub,
            'scope' => array_values($permissions),
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Decode and validate a JWT token
     */
    public function decode(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));

            // Ensure issuer claim is valid
            if (!isset($decoded->iss) || $decoded->iss !== $this->issuer) {
                log_message('warning', "[JWT] Issuer mismatch. Expected: {$this->issuer}");
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            log_message('error', '[JWT] Decode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate if a token is structurally valid and not expired
     */
    public function validate(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    /**
     * Extract user ID from token
     */
    public function getUserId(string $token): ?int
    {
        $decoded = $this->decode($token);
        return isset($decoded->uid) ? (int) $decoded->uid : null;
    }

    /**
     * Extract effective permissions (scope claim) from a token.
     *
     * @return list<string>
     */
    public function getPermissions(string $token)
    {
        $decoded = $this->decode($token);
        if ($decoded === null || ! isset($decoded->scope)) {
            return [];
        }

        $scope = $decoded->scope;
        if (! is_array($scope)) {
            return [];
        }

        return array_values(array_map(static fn ($v) => (string) $v, $scope));
    }
}
