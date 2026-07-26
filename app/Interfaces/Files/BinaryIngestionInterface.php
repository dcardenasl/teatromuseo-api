<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\DTO\Response\Files\FileResponseDTO;
use App\Entities\FileEntity;

interface BinaryIngestionInterface
{
    public function create(FileUploadRequestDTO $request, int $userId, string $visibility): FileResponseDTO;

    public function replace(FileEntity $existing, FileUploadRequestDTO $request, string $visibility): FileResponseDTO;
}
