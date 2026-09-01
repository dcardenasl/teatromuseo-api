<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use App\Support\Files\FileAction;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

interface FilePolicyServiceInterface
{
    public function defaultVisibility(): string;

    public function resolveUploadVisibility(FileUploadRequestDTO $request, ?SecurityContext $context = null): string;

    public function canRead(?SecurityContext $context = null): bool;

    public function canUpload(?SecurityContext $context = null): bool;

    public function canListAllFiles(?SecurityContext $context = null): bool;

    public function shouldScopeListingsToOwner(?SecurityContext $context = null): bool;

    public function canBypassOwnershipForRead(?SecurityContext $context = null): bool;

    public function canAccessFile(FileEntity $file, int $userId, FileAction $action, ?SecurityContext $context = null): bool;
}
