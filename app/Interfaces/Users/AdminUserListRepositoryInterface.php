<?php

declare(strict_types=1);

namespace App\Interfaces\Users;

interface AdminUserListRepositoryInterface
{
    /**
     * @param array<string, mixed> $criteria
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int}
     */
    public function paginateAdminList(array $criteria, int $page = 1, int $perPage = 20): array;
}
