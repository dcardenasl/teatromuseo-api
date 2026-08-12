<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use App\Services\Files\FilePolicyService;
use App\Support\Files\FileAction;
use CodeIgniter\Test\CIUnitTestCase;
use Config\FilePolicy;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

final class FilePolicyServiceTest extends CIUnitTestCase
{
    public function testResolveUploadVisibilityFallsBackToDefaultWhenPublicIsDisabled(): void
    {
        $policy = new FilePolicy();
        $policy->defaultVisibility = 'private';
        $policy->allowPublicVisibility = false;
        $policy->allowedVisibilities = ['private', 'public'];

        $service = new FilePolicyService($policy);
        $tempFile = tempnam(sys_get_temp_dir(), 'file-policy-');
        file_put_contents($tempFile, 'demo');
        try {
            $request = new FileUploadRequestDTO([
                'user_id'    => 1,
                'file'       => [
                    'tmp_name' => $tempFile,
                    'name'     => 'demo.txt',
                    'type'     => 'text/plain',
                    'size'     => 4,
                    'error'    => 0,
                ],
                'visibility' => 'public',
            ]);

            $this->assertSame('private', $service->resolveUploadVisibility($request, null));
        } finally {
            @unlink($tempFile);
        }
    }

    public function testCanListAllFilesRespectsGlobalUnscopedMode(): void
    {
        $policy = new FilePolicy();
        $policy->userScopedFiles = false;

        $service = new FilePolicyService($policy);
        $context = new SecurityContext(7, [], ['files.read']);
        $this->assertTrue($service->canListAllFiles($context));
        $this->assertFalse($service->shouldScopeListingsToOwner($context));
    }

    public function testCanAccessFileAllowsAnyReaderWhenUnscoped(): void
    {
        $policy = new FilePolicy();
        $policy->userScopedFiles = false;
        $service = new FilePolicyService($policy);
        $reader = new SecurityContext(7, [], ['files.read']);

        $file = new FileEntity([
            'id' => 10,
            'user_id' => 22,
        ]);

        $this->assertTrue($service->canAccessFile($file, 7, FileAction::VIEW, $reader));
        $this->assertTrue($service->canAccessFile($file, 7, FileAction::DOWNLOAD, $reader));
        $this->assertFalse($service->canAccessFile($file, 7, FileAction::DELETE, $reader));
    }

    public function testReadPermissionCannotMutateAnotherUsersFile(): void
    {
        $service = new FilePolicyService(new FilePolicy());
        $readerWriter = new SecurityContext(7, [], ['files.read', 'files.write']);
        $file = new FileEntity(['id' => 10, 'user_id' => 22]);

        foreach ([
            FileAction::DELETE,
            FileAction::RESTORE,
            FileAction::FORCE_DELETE,
            FileAction::REPLACE,
            FileAction::UPDATE_METADATA,
            FileAction::REGENERATE_VARIANTS,
        ] as $action) {
            $this->assertFalse(
                $service->canAccessFile($file, 7, $action, $readerWriter),
                $action->value . ' must remain owner/admin protected',
            );
        }
    }

    public function testFilesAdminCanMutateAnotherUsersFile(): void
    {
        $service = new FilePolicyService(new FilePolicy());
        $admin = new SecurityContext(7, [], ['files.admin']);
        $file = new FileEntity(['id' => 10, 'user_id' => 22]);

        $this->assertTrue($service->canAccessFile($file, 7, FileAction::DELETE, $admin));
        $this->assertTrue($service->canAccessFile($file, 7, FileAction::FORCE_DELETE, $admin));
    }

    public function testOwnedMutationRequiresFilesWrite(): void
    {
        $service = new FilePolicyService(new FilePolicy());
        $reader = new SecurityContext(7, [], ['files.read']);
        $file = new FileEntity(['id' => 10, 'user_id' => 7]);

        $this->assertFalse($service->canAccessFile($file, 7, FileAction::DELETE, $reader));
    }
}
