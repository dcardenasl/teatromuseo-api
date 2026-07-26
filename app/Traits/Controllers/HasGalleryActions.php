<?php

declare(strict_types=1);

namespace App\Traits\Controllers;

use App\DTO\Request\Common\GalleryAttachRequestDTO;
use App\DTO\Request\Common\GalleryReorderRequestDTO;
use App\Services\Core\GalleryService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Adds the four standard gallery endpoints to a parent-resource controller.
 *
 * Consumers must implement `galleryService(): GalleryService`, wiring the
 * pivot model and FK column for the specific domain. Suggested route group:
 *
 *   $routes->group('shows/(:num)/images', ['filter' => ['jwtauth']], function ($routes) {
 *       $routes->get('',                  '...ShowController::images/$1');
 *       $routes->post('',                 '...ShowController::attachImage/$1');
 *       $routes->put('reorder',           '...ShowController::reorderImages/$1');
 *       $routes->delete('(:num)',         '...ShowController::detachImage/$1/$2');
 *   });
 */
trait HasGalleryActions
{
    abstract protected function galleryService(): GalleryService;

    public function images(int $parentId): ResponseInterface
    {
        return $this->handleRequest(fn () => array_map(
            static fn ($dto) => $dto->toArray(),
            $this->galleryService()->listFor($parentId)
        ));
    }

    public function attachImage(int $parentId): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto) => $this->galleryService()->attach($parentId, $dto),
            GalleryAttachRequestDTO::class
        );
    }

    public function detachImage(int $parentId, int $pivotId): ResponseInterface
    {
        return $this->handleRequest(fn () => $this->galleryService()->detach($parentId, $pivotId));
    }

    public function reorderImages(int $parentId): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto) => array_map(
                static fn ($img) => $img->toArray(),
                $this->galleryService()->reorder($parentId, $dto)
            ),
            GalleryReorderRequestDTO::class
        );
    }
}
