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

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * NOT NULL columns (code, name, is_system) never accept an explicit
     * null — treated the same as omitting the field. description is the
     * only nullable column: an explicit null preserves through to
     * toArray() and actually clears it — the bug this fixes is
     * array_filter() silently dropping every null, which made it
     * impossible to ever clear description via update.
     */
    protected function map(array $data): void
    {
        $this->application_id = array_key_exists('application_id', $data) && $data['application_id'] !== null && $data['application_id'] !== '' ? (int) $data['application_id'] : null;
        $this->code = array_key_exists('code', $data) && $data['code'] !== null ? (string) $data['code'] : null;
        $this->name = array_key_exists('name', $data) && $data['name'] !== null ? (string) $data['name'] : null;
        $this->description = array_key_exists('description', $data) && $data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null;
        $this->is_system = array_key_exists('is_system', $data) && $data['is_system'] !== null ? (bool) $data['is_system'] : null;
        $this->permission_ids = array_key_exists('permission_ids', $data) ? self::normalizePermissionIds($data['permission_ids']) : null;

        // application_id is excluded — the column was dropped from `roles` by
        // migration 2026-05-03-100006_DropApplicationIdFromRoles (roles became
        // global). The DTO field remains for API/back-compat, but never persists.
        // permission_ids is excluded — handled by RoleService::update via
        // RolePermissionAssignmentService, not by the roles repository.
        $mappedFields = [];
        if ($this->code !== null) {
            $mappedFields['code'] = $this->code;
        }
        if ($this->name !== null) {
            $mappedFields['name'] = $this->name;
        }
        if (array_key_exists('description', $data)) {
            $mappedFields['description'] = $this->description;
        }
        if ($this->is_system !== null) {
            $mappedFields['is_system'] = $this->is_system;
        }

        $this->mappedFields = $mappedFields;
    }

    public function toArray(): array
    {
        return $this->mappedFields;
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
