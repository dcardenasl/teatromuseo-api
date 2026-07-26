<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Entities\FileEntity;
use App\Services\Files\FilePolicyService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\FilePolicy;

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
        $this->assertTrue($service->canListAllFiles(null));
        $this->assertFalse($service->shouldScopeListingsToOwner(null));
    }

    public function testCanAccessFileAllowsAnyReaderWhenUnscoped(): void
    {
        $policy = new FilePolicy();
        $policy->userScopedFiles = false;
        $service = new FilePolicyService($policy);

        $file = new FileEntity([
            'id' => 10,
            'user_id' => 22,
        ]);

        $this->assertTrue($service->canAccessFile($file, 7, 'view', null));
        $this->assertTrue($service->canAccessFile($file, 7, 'download', null));
        $this->assertFalse($service->canAccessFile($file, 7, 'delete', null));
    }
}
