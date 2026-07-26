<?php

declare(strict_types=1);

namespace App\Interfaces\Users;

use App\Entities\UserEntity;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;

/**
 * User Repository Interface
 *
 * Defines user-specific data access methods.
 *
 * @extends RepositoryInterface<UserEntity>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a user by their email address
     */
    public function findByEmail(string $email): ?object;

    /**
     * Find a user by their email address including soft-deleted ones
     */
    public function findByEmailWithDeleted(string $email): ?object;

    /**
     * Find a user by their verification token
     */
    public function findByVerificationToken(string $token): ?object;
}
