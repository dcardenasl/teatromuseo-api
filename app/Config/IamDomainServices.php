<?php

declare(strict_types=1);

namespace Config;

trait IamDomainServices
{
    public static function roleWorkspaceService(bool $getShared = true): \App\Services\Iam\RoleWorkspaceService
    {
        if ($getShared) {
            return static::getSharedInstance('roleWorkspaceService');
        }

        return new \App\Services\Iam\RoleWorkspaceService(model(\App\Models\RoleModel::class));
    }

    public static function roleResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('roleResponseMapper');
        }

        return new \dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\RoleResponseDTO::class
        );
    }

    public static function roleService(bool $getShared = true): \App\Interfaces\Iam\RoleServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('roleService');
        }

        return new \App\Services\Iam\RoleService(
            new \dcardenasl\Ci4ApiCore\Repositories\GenericRepository(model(\App\Models\RoleModel::class)),
            static::roleResponseMapper(),
            static::iamAuthorizationService(),
            static::rolePermissionAssignmentService(),
            static::validation(),
            model(\App\Models\RolePermissionModel::class),
            model(\App\Models\PermissionModel::class)
        );
    }

    public static function rolePermissionAssignmentService(bool $getShared = true): \App\Services\Iam\RolePermissionAssignmentService
    {
        if ($getShared) {
            return static::getSharedInstance('rolePermissionAssignmentService');
        }

        return new \App\Services\Iam\RolePermissionAssignmentService(
            model(\App\Models\RolePermissionModel::class),
            model(\App\Models\RoleModel::class),
            model(\App\Models\PermissionModel::class),
            static::iamAuthorizationService(),
            static::effectivePermissionsResolver()
        );
    }

    public static function permissionResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('permissionResponseMapper');
        }

        return new \dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\PermissionResponseDTO::class
        );
    }

    public static function permissionService(bool $getShared = true): \App\Interfaces\Iam\PermissionServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('permissionService');
        }

        return new \App\Services\Iam\PermissionService(
            new \dcardenasl\Ci4ApiCore\Repositories\GenericRepository(model(\App\Models\PermissionModel::class)),
            static::permissionResponseMapper(),
            static::iamAuthorizationService(),
            static::validation(),
            static::request(),
            static::apiKeyRepository()
        );
    }

    public static function applicationResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationResponseMapper');
        }

        return new \dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\ApplicationResponseDTO::class
        );
    }

    public static function applicationService(bool $getShared = true): \App\Interfaces\Iam\ApplicationServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationService');
        }

        return new \App\Services\Iam\ApplicationService(
            new \dcardenasl\Ci4ApiCore\Repositories\GenericRepository(model(\App\Models\ApplicationModel::class)),
            static::applicationResponseMapper()
        );
    }

    public static function effectivePermissionsResolver(bool $getShared = true): \App\Services\Iam\EffectivePermissionsResolver
    {
        if ($getShared) {
            return static::getSharedInstance('effectivePermissionsResolver');
        }

        return new \App\Services\Iam\EffectivePermissionsResolver(
            model(\App\Models\UserRoleModel::class),
            model(\App\Models\PermissionModel::class),
            static::cache()
        );
    }

    public static function applicationPermissionsResolver(bool $getShared = true): \App\Interfaces\Iam\ApplicationPermissionsResolverInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationPermissionsResolver');
        }

        return new \App\Services\Iam\ApplicationPermissionsResolver(
            model(\App\Models\PermissionModel::class),
            static::cache()
        );
    }

    public static function userRoleAssignmentService(bool $getShared = true): \App\Services\Iam\UserRoleAssignmentService
    {
        if ($getShared) {
            return static::getSharedInstance('userRoleAssignmentService');
        }

        return new \App\Services\Iam\UserRoleAssignmentService(
            model(\App\Models\UserRoleModel::class),
            model(\App\Models\RoleModel::class),
            model(\App\Models\RolePermissionModel::class),
            static::effectivePermissionsResolver()
        );
    }

    public static function iamAuthorizationService(bool $getShared = true): \App\Services\Iam\IamAuthorizationService
    {
        if ($getShared) {
            return static::getSharedInstance('iamAuthorizationService');
        }

        return new \App\Services\Iam\IamAuthorizationService(
            static::effectivePermissionsResolver(),
            static::securityAuditLogger(),
            model(\App\Models\RoleModel::class),
            model(\App\Models\PermissionModel::class),
            model(\App\Models\RolePermissionModel::class)
        );
    }

    public static function assignableRolesService(bool $getShared = true): \App\Services\Iam\AssignableRolesService
    {
        if ($getShared) {
            return static::getSharedInstance('assignableRolesService');
        }

        return new \App\Services\Iam\AssignableRolesService(
            model(\App\Models\RoleModel::class),
            model(\App\Models\RolePermissionModel::class)
        );
    }

    public static function userPermissionsService(bool $getShared = true): \App\Services\Iam\UserPermissionsService
    {
        if ($getShared) {
            return static::getSharedInstance('userPermissionsService');
        }

        return new \App\Services\Iam\UserPermissionsService(
            static::effectivePermissionsResolver(),
            model(\App\Models\UserModel::class),
            model(\App\Models\ApplicationModel::class)
        );
    }

    public static function rolePermissionMatrixService(bool $getShared = true): \App\Services\Iam\RolePermissionMatrixService
    {
        if ($getShared) {
            return static::getSharedInstance('rolePermissionMatrixService');
        }

        return new \App\Services\Iam\RolePermissionMatrixService(
            model(\App\Models\ApplicationModel::class),
            model(\App\Models\PermissionModel::class),
            model(\App\Models\RoleModel::class),
            model(\App\Models\RolePermissionModel::class)
        );
    }

    public static function selfPermissionService(bool $getShared = true): \App\Libraries\Iam\SelfPermissionService
    {
        if ($getShared) {
            return static::getSharedInstance('selfPermissionService');
        }

        return new \App\Libraries\Iam\SelfPermissionService(
            model(\App\Models\PermissionModel::class),
            model(\App\Models\ApplicationModel::class)
        );
    }
}
