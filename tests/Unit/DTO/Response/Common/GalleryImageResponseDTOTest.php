<?php

declare(strict_types=1);

namespace Tests\Unit\DTO\Response\Common;

use App\DTO\Response\Common\GalleryImageResponseDTO;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class GalleryImageResponseDTOTest extends CIUnitTestCase
{
    public function testFromArrayAcceptsJsonEncodedVariants(): void
    {
        $dto = GalleryImageResponseDTO::fromArray([
            'id'         => 42,
            'parent_id'  => 7,
            'file_id'    => '5',
            'sort_order' => 0,
            'is_active'  => true,
            'variants'   => '{"thumb":"/files/5/thumb.jpg","medium":"/files/5/medium.jpg"}',
        ]);

        $this->assertSame(42, $dto->id);
        $this->assertSame([
            'thumb'  => '/files/5/thumb.jpg',
            'medium' => '/files/5/medium.jpg',
        ], $dto->variants);
    }

    public function testFromArrayHandlesArrayVariants(): void
    {
        $dto = GalleryImageResponseDTO::fromArray([
            'id'         => 1,
            'parent_id'  => 1,
            'file_id'    => '1',
            'sort_order' => 1,
            'is_active'  => true,
            'variants'   => ['thumb' => '/x.jpg'],
        ]);

        $this->assertSame(['thumb' => '/x.jpg'], $dto->variants);
    }

    public function testFromArrayCoercesNonArrayVariantsToNull(): void
    {
        $dto = GalleryImageResponseDTO::fromArray([
            'id'         => 1,
            'parent_id'  => 1,
            'file_id'    => '1',
            'sort_order' => 0,
            'is_active'  => true,
            'variants'   => 'not-json',
        ]);

        $this->assertNull($dto->variants);
    }

    public function testToArrayOmitsOptionalFieldsWhenNull(): void
    {
        $dto = GalleryImageResponseDTO::fromArray([
            'id'         => 5,
            'parent_id'  => 1,
            'file_id'    => '9',
            'sort_order' => 0,
            'is_active'  => false,
        ]);

        $this->assertSame([
            'id'         => 5,
            'parent_id'  => 1,
            'file_id'    => '9',
            'sort_order' => 0,
            'is_active'  => false,
        ], $dto->toArray());
    }

    public function testToArrayIncludesOptionalFieldsWhenSet(): void
    {
        $dto = GalleryImageResponseDTO::fromArray([
            'id'            => 5,
            'parent_id'     => 1,
            'file_id'       => '9',
            'sort_order'    => 2,
            'is_active'     => true,
            'original_name' => 'photo.jpg',
            'is_image'      => true,
            'variants'      => ['thumb' => '/t.jpg'],
        ]);

        $this->assertSame([
            'id'            => 5,
            'parent_id'     => 1,
            'file_id'       => '9',
            'sort_order'    => 2,
            'is_active'     => true,
            'original_name' => 'photo.jpg',
            'is_image'      => true,
            'variants'      => ['thumb' => '/t.jpg'],
        ], $dto->toArray());
    }
}
