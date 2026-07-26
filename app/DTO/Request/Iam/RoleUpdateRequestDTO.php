<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RoleUpdateRequest')]
readonly class RoleUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Application id; null for global roles', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'Role code (unique within application)', type: 'string', nullable: true)]
    public ?string $code;
    #[OA\Property(description: 'Display name', type: 'string', nullable: true)]
    public ?string $name;
    #[OA\Property(description: 'Free-form description', type: 'string', nullable: true)]
    public ?string $description;
    #[OA\Property(description: 'System role (cannot be deleted)', type: 'boolean', nullable: true)]
    public ?bool $is_system;

    /** @var list<int>|null */
    #[OA\Property(
        description: 'Replace the role permission set with this list of permission ids. Omit to leave permissions unchanged. Empty list removes all permissions.',
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
            'code' => 'permit_empty|string|max_length[100]',
            'name' => 'permit_empty|string|max_length[100]',
            'description' => 'permit_empty|string',
            'is_system' => 'permit_empty|in_list[0,1]',
            'permission_ids' => 'permit_empty',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->code = isset($data['code']) ? (string) $data['code'] : null;
        $this->name = isset($data['name']) ? (string) $data['name'] : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->is_system = isset($data['is_system']) ? (bool) $data['is_system'] : null;
        $this->permission_ids = array_key_exists('permission_ids', $data)
            ? self::normalizePermissionIds($data['permission_ids'])
            : null;
    }

    public function toArray(): array
    {
        // application_id is excluded — the column was dropped from `roles` by
        // migration 2026-05-03-100006_DropApplicationIdFromRoles (roles became
        // global). The DTO field remains for API/back-compat, but never persists.
        // permission_ids is excluded — handled by RoleService::update via
        // RolePermissionAssignmentService, not by the roles repository.
        return array_filter([
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
        ], fn ($v) => $v !== null);
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
