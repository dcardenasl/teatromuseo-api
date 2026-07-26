<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

interface FilePolicyServiceInterface
{
    public function defaultVisibility(): string;

    public function resolveUploadVisibility(FileUploadRequestDTO $request, ?SecurityContext $context = null): string;

    public function canListAllFiles(?SecurityContext $context = null): bool;

    public function shouldScopeListingsToOwner(?SecurityContext $context = null): bool;

    public function canBypassOwnershipForRead(?SecurityContext $context = null): bool;

    public function canAccessFile(FileEntity $file, int $userId, string $action, ?SecurityContext $context = null): bool;
}
