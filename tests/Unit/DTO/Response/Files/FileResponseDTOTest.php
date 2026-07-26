<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Response\Files;

use App\DTO\Response\Files\FileResponseDTO;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FileResponseDTOTest extends CIUnitTestCase
{
    public function testFromArrayDerivesImageCategoryFromMimeType(): void
    {
        $dto = FileResponseDTO::fromArray([
            'id'            => 1,
            'original_name' => 'photo.png',
            'stored_name'   => 'photo.png',
            'mime_type'     => 'image/png',
            'category'      => 'document',
            'size'          => 70,
            'url'           => '/files/1',
        ]);

        $this->assertSame('image', $dto->category);
    }

    public function testFromArrayKeepsDocumentCategoryForDocumentMimeType(): void
    {
        $dto = FileResponseDTO::fromArray([
            'id'            => 2,
            'original_name' => 'report.pdf',
            'stored_name'   => 'report.pdf',
            'mime_type'     => 'application/pdf',
            'category'      => 'document',
            'size'          => 466,
            'url'           => '/files/2',
        ]);

        $this->assertSame('document', $dto->category);
    }
}
