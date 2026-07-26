<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RoleCreateRequest')]
readonly class RoleCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Application id; null for global roles', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'Role code (unique within application)', type: 'string')]
    public string $code;
    #[OA\Property(description: 'Display name', type: 'string')]
    public string $name;
    #[OA\Property(description: 'Free-form description', type: 'string')]
    public string $description;
    #[OA\Property(description: 'System role (cannot be deleted)', type: 'boolean')]
    public bool $is_system;

    /** @var list<int>|null */
    #[OA\Property(
        description: 'Optional list of permission ids to attach to the new role. Omit to create a role with no permissions.',
        type: 'array',
        items: new OA\Items(type: 'integer'),
        example: [1, 2],
        nullable: true
    )]
    public ?array $permission_ids;

    public function rules(): array
    {
        return [
            'application_id' => 'permit_empty|integer',
            'code' => 'required|string|max_length[100]',
            'name' => 'required|string|max_length[100]',
            'description' => 'permit_empty|string',
            'is_system' => 'permit_empty|in_list[0,1]',
            'permission_ids' => 'permit_empty',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->code = (string) ($data['code'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
        $this->is_system = (bool) ($data['is_system'] ?? false);
        $this->permission_ids = array_key_exists('permission_ids', $data)
            ? self::normalizePermissionIds($data['permission_ids'])
            : null;
    }

    public function toArray(): array
    {
        // application_id is excluded — the column was dropped from `roles` by
        // migration 2026-05-03-100006_DropApplicationIdFromRoles (roles became
        // global). The DTO field remains for API/back-compat, but never persists.
        // permission_ids is excluded — handled by RoleService::store via
        // RolePermissionAssignmentService, not by the roles repository.
        return [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
        ];
    }

    /**
     * @return list<int>
     */
    private static function normalizePermissionIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $clean[] = (int) $value;
            }
        }
        return array_values(array_unique($clean));
    }
}
