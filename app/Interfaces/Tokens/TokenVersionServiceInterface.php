<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

interface TokenVersionServiceInterface
{
    public function current(int $userId): int;

    public function increment(int $userId): int;

    public function matches(int $userId, int $tokenVersion): bool;
}
