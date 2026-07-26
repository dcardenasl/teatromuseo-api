<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Files;

use App\DTO\Request\Files\FileBulkActionRequestDTO;
use App\DTO\Request\Files\FileGetRequestDTO;
use App\DTO\Request\Files\FileIndexRequestDTO;
use App\DTO\Request\Files\FileUploadRequestDTO;
use App\DTO\Request\Files\UpdateFileMetadataRequestDTO;
use App\DTO\Response\Files\FileDownloadResponseDTO;
use App\Interfaces\Files\FileServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * File Controller
 *
 * Handles file uploads, listing, downloading, and deletion.
 * Uses automated DTO validation and user context injection.
 */
class FileController extends ApiController
{
    protected FileServiceInterface $fileService;

    protected function resolveDefaultService(): object
    {
        $this->fileService = Services::fileService();

        return $this->fileService;
    }

    /**
     * Map upload to 201 Created status
     */
    protected array $statusCodes = [
        'upload' => 201,
        'store'  => 201,
    ];

    /**
     * List user's files
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', FileIndexRequestDTO::class);
    }

    /**
     * Upload a new file
     */
    public function upload(): ResponseInterface
    {
        return $this->handleRequest('upload', FileUploadRequestDTO::class);
    }

    /**
     * Download a file
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            /** @var FileDownloadResponseDTO $result */
            $result = $this->fileService->download($dto, $context);
            $payload = $result->toArray();

            // For local storage, send file for direct download
            if ($result->storage_driver === 'local') {
                $filePath = FCPATH . config('Api')->fileUploadPath . $result->path;

                if (file_exists($filePath)) {
                    $download = $this->response->download($filePath, null);
                    if ($download !== null) {
                        return $download->setFileName($result->original_name);
                    }
                }
            }

            // For external storage like S3, just return the data (with URL)
            return ApiResponse::success($payload);
        }, FileGetRequestDTO::class, ['id' => $id]);
    }

    /**
     * Return JSON metadata for a single file — no binary download.
     */
    public function info(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->findById($id, $context)
        );
    }

    /**
     * Return the list of resources that reference this file.
     */
    public function usages(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->getUsages($id, $context)
        );
    }

    /**
     * Delete existing image variants and regenerate them from the stored original.
     */
    public function regenerateVariants(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->regenerateVariants($id, $context)
        );
    }

    /**
     * Replace a file's binary content while preserving its ID and references.
     */
    public function replace(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->replace($id, $dto, $context),
            FileUploadRequestDTO::class
        );
    }

    /**
     * Update editable metadata fields (original_name, alt_text, caption, credit, category).
     */
    public function metadata(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->updateMetadata($id, $dto, $context),
            UpdateFileMetadataRequestDTO::class
        );
    }

    /**
     * Soft-delete a file (moves it to the trash).
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->fileService->destroy($id, $context));
    }

    /**
     * Restore a trashed file.
     */
    public function restore(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->fileService->restore($id, $context));
    }

    /**
     * Permanently delete a trashed file (storage + DB).
     */
    public function forceDelete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->fileService->forceDestroy($id, $context));
    }

    public function bulkDelete(): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->bulkDestroy($dto->ids, $context),
            FileBulkActionRequestDTO::class
        );
    }

    public function bulkRestore(): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->bulkRestore($dto->ids, $context),
            FileBulkActionRequestDTO::class
        );
    }

    public function bulkForceDelete(): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->fileService->bulkForceDestroy($dto->ids, $context),
            FileBulkActionRequestDTO::class
        );
    }
}
