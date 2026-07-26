<?php

declare(strict_types=1);

namespace App\Models;

use dcardenasl\Ci4ApiCore\Security\Hasher;

/**
 * Refresh Token Model
 *
 * Manages refresh tokens for JWT authentication
 */
class RefreshTokenModel extends \dcardenasl\Ci4ApiCore\Models\BaseAuditableModel
{
    protected $table = 'refresh_tokens';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'token',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    // No timestamps (using custom created_at)
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';

    // Validation rules
    protected $validationRules = [
        'user_id' => 'required|integer',
        'token' => 'required|max_length[255]|is_unique[refresh_tokens.token]',
        'expires_at' => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get active refresh token for user
     *
     * @param string $token
     * @return object|null
     */
    public function getActiveToken(string $token): ?object
    {
        $tokenHash = Hasher::token($token);
        /** @var object|null */
        return $this->where('token', $tokenHash)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->where('revoked_at', null)
            ->first();
    }

    /**
     * Revoke a refresh token
     *
     * @param string $token
     * @return bool Returns true if token exists, false if token doesn't exist
     */
    public function revokeToken(string $token): bool
    {
        $tokenHash = Hasher::token($token);
        // Check if token exists first
        $tokenExists = $this->where('token', $tokenHash)->countAllResults(false) > 0;

        if (!$tokenExists) {
            return false;
        }

        // Update only non-revoked tokens, but return true since token exists
        $this->where('token', $tokenHash)
            ->where('revoked_at', null)
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update();

        return true;
    }

    /**
     * Revoke all user's refresh tokens
     *
     * @param int $userId
     * @return bool
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        $this->where('user_id', $userId)
            ->where('revoked_at', null)
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Get active refresh token with row-level lock (FOR UPDATE)
     */
    public function findActiveForUpdate(string $token): ?object
    {
        $tokenHash = Hasher::token($token);
        $sql = $this->where('token', $tokenHash)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->where('revoked_at', null)
            ->builder()
            ->getCompiledSelect() . ' FOR UPDATE';

        $result = $this->db->query($sql);

        if (!$result instanceof \CodeIgniter\Database\ResultInterface) {
            return null;
        }

        /** @var object|null $row */
        $row = $result->getFirstRow('object');

        return $row;
    }
    /**
     * Delete expired tokens
     */
    public function deleteExpired(): int
    {
        $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();
        return $this->db->affectedRows();
    }
}
