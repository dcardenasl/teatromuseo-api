<?php

declare(strict_types=1);

namespace App\Interfaces\Admin;

interface DashboardSummaryRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function read(): array;
}
