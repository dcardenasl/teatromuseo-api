<?php

declare(strict_types=1);

namespace Config;

class FilePolicy extends \CodeIgniter\Config\BaseConfig
{
    /**
     * Default visibility assigned on upload when the caller does not provide
     * an explicit value or when the provided value is rejected by policy.
     */
    public string $defaultVisibility = 'private';

    /**
     * Whether file listings should be restricted to the owning user by
     * default. When false, callers with the right permissions can list all
     * files.
     */
    public bool $userScopedFiles = false;

    /**
     * Whether view/download actions may bypass ownership checks when the
     * caller has elevated file-read permissions.
     */
    public bool $allowPrivilegedReadBypass = true;

    /**
     * Whether trusted callers may create public files.
     */
    public bool $allowPublicVisibility = true;

    /**
     * Optional allow-list of visibility values accepted by the policy layer.
     *
     * @var list<string>
     */
    public array $allowedVisibilities = ['private', 'public'];

    public function __construct()
    {
        parent::__construct();

        $this->defaultVisibility = $this->normalizeVisibility((string) env('FILE_DEFAULT_VISIBILITY', $this->defaultVisibility));
        $this->userScopedFiles = filter_var(
            env('FILE_USER_SCOPED_FILES', $this->userScopedFiles),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? $this->userScopedFiles;
        $this->allowPrivilegedReadBypass = filter_var(
            env('FILE_ALLOW_PRIVILEGED_READ_BYPASS', $this->allowPrivilegedReadBypass),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? $this->allowPrivilegedReadBypass;
        $this->allowPublicVisibility = filter_var(
            env('FILE_ALLOW_PUBLIC_VISIBILITY', $this->allowPublicVisibility),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? $this->allowPublicVisibility;

        $allowed = array_filter(array_map(
            static fn (string $visibility): string => strtolower(trim($visibility)),
            array_map('trim', explode(',', (string) env('FILE_ALLOWED_VISIBILITY', implode(',', $this->allowedVisibilities))))
        ));

        $allowed = array_values(array_unique(array_map(
            fn (string $visibility): string => $this->normalizeVisibility($visibility),
            $allowed
        )));

        if ($allowed !== []) {
            $this->allowedVisibilities = $allowed;
        }
    }

    public function normalizeVisibility(?string $visibility): string
    {
        $visibility = strtolower(trim((string) $visibility));

        return match ($visibility) {
            'public' => 'public',
            default  => 'private',
        };
    }
}
