<?php

declare(strict_types=1);

namespace Config;

trait FileDomainServices
{
    public static function fileService(bool $getShared = true): \App\Interfaces\Files\FileServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('fileService');
        }

        $storage = static::storageManager();
        $imageVariantProcessor = new \App\Libraries\Files\ImageVariantProcessor();
        $binaryIngestion = new \App\Services\Files\FileBinaryIngestor(
            static::fileRepository(),
            static::fileResponseMapper(),
            $storage,
            new \App\Libraries\Files\StorageKeyGenerator(),
            new \App\Libraries\Files\MultipartProcessor(),
            new \App\Libraries\Files\Base64Processor(),
            $imageVariantProcessor,
            static::virusScannerService(),
        );

        return new \App\Services\Files\FileService(
            static::fileRepository(),
            static::fileResponseMapper(),
            $storage,
            static::auditService(),
            $imageVariantProcessor,
            static::fileReferenceRepository(),
            static::filePolicyService(),
            $binaryIngestion,
            static::domainFileUsageClient(),
            new \App\Libraries\Files\FilePickerManifestCache(static::cache()),
        );
    }

    public static function filePickerManifestCache(bool $getShared = true): \App\Libraries\Files\FilePickerManifestCache
    {
        if ($getShared) {
            return static::getSharedInstance('filePickerManifestCache');
        }

        return new \App\Libraries\Files\FilePickerManifestCache(static::cache());
    }

    public static function domainFileUsageClient(bool $getShared = true): \App\Interfaces\Files\DomainFileUsageClientInterface
    {
        if ($getShared) {
            return static::getSharedInstance('domainFileUsageClient');
        }

        return new \App\Libraries\Domains\DomainFileUsageClient(config('DomainWebhooks'));
    }

    public static function filePolicyService(bool $getShared = true): \App\Interfaces\Files\FilePolicyServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('filePolicyService');
        }

        return new \App\Services\Files\FilePolicyService(config('FilePolicy'));
    }

    public static function fileResponseMapper(bool $getShared = true): \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('fileResponseMapper');
        }

        return new \App\Mappers\Files\FileResponseMapper(static::storageManager());
    }

    public static function virusScannerService(bool $getShared = true): \App\Interfaces\Files\VirusScannerServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('virusScannerService');
        }

        return new \App\Services\Files\ClamAvScannerService(
            static::logger(),
            (bool) env('FILES_VIRUS_SCAN_ENABLED', false),
            (string) env('FILES_CLAMAV_ADDRESS', 'tcp://127.0.0.1:3310')
        );
    }

    public static function storageManager(bool $getShared = true): \App\Libraries\Storage\StorageManager
    {
        if ($getShared) {
            return static::getSharedInstance('storageManager');
        }

        return new \App\Libraries\Storage\StorageManager();
    }
}
