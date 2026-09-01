<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use App\Interfaces\Files\FilePolicyServiceInterface;
use App\Support\Files\FileAction;
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
        return $this->canRead($context);
    }

    public function shouldScopeListingsToOwner(?SecurityContext $context = null): bool
    {
        return ! $this->canListAllFiles($context);
    }

    public function canBypassOwnershipForRead(?SecurityContext $context = null): bool
    {
        if ($context?->hasPermission('files.read') !== true) {
            return false;
        }

        return ! $this->policy->userScopedFiles || $this->policy->allowPrivilegedReadBypass;
    }

    public function canRead(?SecurityContext $context = null): bool
    {
        return $context?->hasPermission('files.read') === true;
    }

    public function canUpload(?SecurityContext $context = null): bool
    {
        return $context?->hasPermission('files.write') === true
            || $context?->hasPermission('files.admin') === true;
    }

    public function canAccessFile(FileEntity $file, int $userId, FileAction $action, ?SecurityContext $context = null): bool
    {
        if ($action->isRead()) {
            if (! $this->canRead($context)) {
                return false;
            }

            return (int) $file->user_id === $userId
                || $this->canBypassOwnershipForRead($context);
        }

        if ($action === FileAction::FORCE_DELETE) {
            return (int) $file->user_id === $userId
                ? $this->canUpload($context)
                : $context?->hasPermission('files.admin') === true;
        }

        if ((int) $file->user_id === $userId) {
            return $this->canUpload($context);
        }

        return $context?->hasPermission('files.admin') === true;
    }
}
