<?php

declare(strict_types=1);

namespace Config;

trait RepositoryModelServices
{
    public static function userRepository(bool $getShared = true): \App\Interfaces\Users\UserRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('userRepository');
        }

        return new \App\Repositories\Users\UserRepository(static::userModel());
    }

    public static function auditRepository(bool $getShared = true): \dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditRepository');
        }

        return new \App\Repositories\System\AuditRepository(model(\App\Models\AuditLogModel::class));
    }

    public static function fileRepository(bool $getShared = true): \App\Interfaces\Files\FileRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('fileRepository');
        }

        return new \App\Repositories\Files\FileRepository(model(\App\Models\FileModel::class));
    }

    public static function fileReferenceRepository(bool $getShared = true): \App\Interfaces\Files\FileReferenceRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('fileReferenceRepository');
        }

        return new \App\Repositories\Files\FileReferenceRepository(model(\App\Models\Files\FileReferenceModel::class));
    }

    public static function userModel(bool $getShared = true): \App\Models\UserModel
    {
        if ($getShared) {
            return static::getSharedInstance('userModel');
        }

        return new \App\Models\UserModel();
    }

    public static function apiKeyModel(bool $getShared = true): \App\Models\ApiKeyModel
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyModel');
        }

        return new \App\Models\ApiKeyModel();
    }

    public static function apiKeyRepository(bool $getShared = true): \App\Interfaces\Tokens\ApiKeyRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyRepository');
        }

        return new \App\Repositories\Tokens\ApiKeyRepository(static::apiKeyModel());
    }
}
