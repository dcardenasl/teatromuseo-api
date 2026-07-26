<?php

declare(strict_types=1);

namespace App\Libraries\Iam;

use App\Models\ApplicationModel;
use App\Models\PermissionModel;
use CodeIgniter\Entity\Entity;
use Config\Services;

/**
 * Allows a domain app (authenticated via X-App-Key) to register its own
 * permissions in the hub without requiring a superadmin JWT.
 *
 * Security: each permission code MUST start with "{app.code}." — a domain app
 * registered as "catalog" can only register "catalog.*" codes. Codes that fail
 * this namespace check are rejected and counted separately.
 */
class SelfPermissionService
{
    public function __construct(
        private readonly PermissionModel $permissionModel,
        private readonly ApplicationModel $applicationModel,
    ) {
    }

    /**
     * @param list<array<string, string>> $permissions
     */
    public function sync(int $appId, array $permissions): SelfPermissionResult
    {
        $application = $this->applicationModel->find($appId);
        if ($application === null) {
            return new SelfPermissionResult(0, 0, count($permissions), ['Application not found']);
        }

        /** @var \App\Entities\ApplicationEntity $application */
        $namespace = $application->code . '.';
        $created   = 0;
        $existing  = 0;
        $permissionIds = [];
        $rejected  = 0;
        $errors    = [];

        foreach ($permissions as $perm) {
            $code = trim((string) ($perm['code'] ?? ''));

            if ($code === '' || !str_starts_with($code, $namespace)) {
                $rejected++;
                $errors[] = "Rejected '{$code}': must start with '{$namespace}'";
                continue;
            }

            $existingRow = $this->permissionModel
                ->where('application_id', $appId)
                ->where('code', $code)
                ->first();

            if ($existingRow !== null) {
                $existing++;
                $permissionIds[] = $this->permissionId($existingRow);
                continue;
            }

            $inserted = $this->permissionModel->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => (string) ($perm['resource'] ?? ''),
                'action'         => (string) ($perm['action'] ?? ''),
                'description'    => (string) ($perm['description'] ?? ''),
            ]);

            if ($inserted === false || $inserted === 0) {
                $rejected++;
                $errors[] = "Failed to insert '{$code}': " . implode(', ', $this->permissionModel->errors());
            } else {
                $created++;
                $permissionIds[] = (int) $inserted;
            }
        }

        (new SuperadminPermissionAttacher())->attach($permissionIds);
        Services::effectivePermissionsResolver()->invalidateAll();
        Services::applicationPermissionsResolver()->invalidate($appId);

        return new SelfPermissionResult($created, $existing, $rejected, $errors);
    }

    /**
     * @param array<mixed>|object $row
     */
    private function permissionId(array|object $row): int
    {
        if (is_array($row)) {
            return (int) ($row['id'] ?? 0);
        }

        if ($row instanceof Entity) {
            return (int) ($row->toRawArray()['id'] ?? 0);
        }

        return (int) ($row->id ?? 0);
    }
}
