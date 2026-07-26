<?php

declare(strict_types=1);

namespace Config;

trait IamDomainServices
{
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
            \Config\Database::connect()
        );
    }

    public static function rolePermissionAssignmentService(bool $getShared = true): \App\Services\Iam\RolePermissionAssignmentService
    {
        if ($getShared) {
            return static::getSharedInstance('rolePermissionAssignmentService');
        }

        return new \App\Services\Iam\RolePermissionAssignmentService(
            \Config\Database::connect(),
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
            \Config\Database::connect(),
            static::cache()
        );
    }

    public static function applicationPermissionsResolver(bool $getShared = true): \App\Interfaces\Iam\ApplicationPermissionsResolverInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationPermissionsResolver');
        }

        return new \App\Services\Iam\ApplicationPermissionsResolver(
            \Config\Database::connect(),
            static::cache()
        );
    }

    public static function userRoleAssignmentService(bool $getShared = true): \App\Services\Iam\UserRoleAssignmentService
    {
        if ($getShared) {
            return static::getSharedInstance('userRoleAssignmentService');
        }

        return new \App\Services\Iam\UserRoleAssignmentService(
            \Config\Database::connect(),
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
            \Config\Database::connect()
        );
    }

    public static function assignableRolesService(bool $getShared = true): \App\Services\Iam\AssignableRolesService
    {
        if ($getShared) {
            return static::getSharedInstance('assignableRolesService');
        }

        return new \App\Services\Iam\AssignableRolesService(
            \Config\Database::connect()
        );
    }

    public static function userPermissionsService(bool $getShared = true): \App\Services\Iam\UserPermissionsService
    {
        if ($getShared) {
            return static::getSharedInstance('userPermissionsService');
        }

        return new \App\Services\Iam\UserPermissionsService(
            static::effectivePermissionsResolver(),
            \Config\Database::connect()
        );
    }

    public static function rolePermissionMatrixService(bool $getShared = true): \App\Services\Iam\RolePermissionMatrixService
    {
        if ($getShared) {
            return static::getSharedInstance('rolePermissionMatrixService');
        }

        return new \App\Services\Iam\RolePermissionMatrixService(\Config\Database::connect());
    }
}
