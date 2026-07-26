<?php

declare(strict_types=1);

namespace App\Services\Tokens\Support;

readonly class ApiKeyMaterialService
{
    public function generateRawKey(): string
    {
        return 'apk_' . bin2hex(random_bytes(24));
    }

    public function hash(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }
}
