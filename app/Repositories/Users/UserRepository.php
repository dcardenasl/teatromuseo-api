<?php

declare(strict_types=1);

namespace App\Repositories\Users;

use App\Entities\UserEntity;
use App\Interfaces\Users\UserRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;
use dcardenasl\Ci4ApiCore\Security\Hasher;

/**
 * User Repository (Implementation)
 *
 * @extends BaseRepository<UserEntity>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?object
    {
        /** @var UserEntity|null $user */
        $user = $this->model->where('email', $email)->first();

        return $user;
    }

    public function findByEmailWithDeleted(string $email): ?object
    {
        /** @var UserEntity|null $user */
        $user = $this->model->withDeleted()->where('email', $email)->first();

        return $user;
    }

    public function findByVerificationToken(string $token): ?object
    {
        $tokenHash = Hasher::token($token);
        /** @var UserEntity|null $user */
        $user = $this->model->where('email_verification_token', $tokenHash)->first();

        return $user;
    }
}
