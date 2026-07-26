<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use App\Interfaces\Files\FilePolicyServiceInterface;
use Config\FilePolicy;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

class FilePolicyService implements FilePolicyServiceInterface
{
    public function __construct(private readonly FilePolicy $policy)
    {
    }

    public function defaultVisibility(): string
    {
        return $this->policy->defaultVisibility;
    }

    public function resolveUploadVisibility(FileUploadRequestDTO $request, ?SecurityContext $context = null): string
    {
        $requested = $this->policy->normalizeVisibility((string) ($request->visibility ?? $this->policy->defaultVisibility));

        if (! in_array($requested, $this->policy->allowedVisibilities, true)) {
            return $this->policy->defaultVisibility;
        }

        if ($requested === 'public' && ! $this->policy->allowPublicVisibility) {
            return $this->policy->defaultVisibility;
        }

        return $requested;
    }

    public function canListAllFiles(?SecurityContext $context = null): bool
    {
        if (! $this->policy->userScopedFiles) {
            return true;
        }

        return $context?->hasPermission('files.read') === true;
    }

    public function shouldScopeListingsToOwner(?SecurityContext $context = null): bool
    {
        return ! $this->canListAllFiles($context);
    }

    public function canBypassOwnershipForRead(?SecurityContext $context = null): bool
    {
        if (! $this->policy->userScopedFiles) {
            return true;
        }

        return $this->policy->allowPrivilegedReadBypass && $context?->hasPermission('files.read') === true;
    }

    public function canAccessFile(FileEntity $file, int $userId, string $action, ?SecurityContext $context = null): bool
    {
        if (! $this->policy->userScopedFiles && in_array($action, ['download', 'view'], true)) {
            return true;
        }

        if ((int) $file->user_id === $userId) {
            return true;
        }

        if (in_array($action, ['download', 'view'], true) && $this->canBypassOwnershipForRead($context)) {
            return true;
        }

        return false;
    }
}
