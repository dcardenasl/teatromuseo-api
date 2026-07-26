<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Libraries\Storage\StorageManager;

class ImageVariantProcessor
{
    public const PROCESSABLE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const VARIANTS = [
        'thumb' => ['width' => 150, 'height' => null, 'mode' => 'fit'],
        'sm'    => ['width' => 400, 'height' => null, 'mode' => 'fit'],
        'md'    => ['width' => 800, 'height' => null, 'mode' => 'fit'],
        'lg'    => ['width' => 1200, 'height' => null, 'mode' => 'fit'],
    ];

    private const WEBP_QUALITY = 82;
    private const JPEG_QUALITY = 85;
    private const PNG_QUALITY  = 9;

    /**
     * Generate thumb/sm/md variants for an image already in storage.
     *
     * @return array{variants: array<string, array{path: string, url: string, width: int, height: int}>, dimensions: array{width: int|null, height: int|null}}
     */
    public function generate(string $originalPath, string $extension, StorageManager $storage): array
    {
        $originalDimensions = ['width' => null, 'height' => null];
        $variants           = [];
        $tmpOriginal        = null;

        try {
            $contents = $storage->get($originalPath);
            if ($contents === false) {
                log_message('error', "ImageVariantProcessor: could not read {$originalPath}");
                return ['variants' => [], 'dimensions' => $originalDimensions];
            }

            $extension   = strtolower($extension);
            $uid         = bin2hex(random_bytes(8));
            $tmpDir      = sys_get_temp_dir();
            $tmpOriginal = $tmpDir . DIRECTORY_SEPARATOR . 'ci4_img_' . $uid . '.' . $extension;

            if (file_put_contents($tmpOriginal, $contents) === false) {
                return ['variants' => [], 'dimensions' => $originalDimensions];
            }

            $origSize = @getimagesize($tmpOriginal);
            if ($origSize !== false) {
                $originalDimensions = ['width' => $origSize[0], 'height' => $origSize[1]];
            }

            if ($extension === 'gif') {
                return ['variants' => [], 'dimensions' => $originalDimensions];
            }

            $dir      = dirname($originalPath);
            $basename = pathinfo($originalPath, PATHINFO_FILENAME);
            $dir      = $dir !== '.' ? $dir . '/' : '';

            foreach (self::VARIANTS as $key => $spec) {
                $target       = $this->targetDimensions($originalDimensions, $spec['width']);
                $outputFormat = $this->outputFormatFor($extension);
                $tmpOutput    = $tmpDir . DIRECTORY_SEPARATOR . 'ci4_var_' . $uid . '_' . $key . '.' . $outputFormat;

                try {
                    $imageLib = \Config\Services::image('gd', null, false);
                    $imageLib->withFile($tmpOriginal);

                    $imageLib->resize($target['width'], $target['height'], true);

                    $imageLib->save($tmpOutput, $this->qualityFor($outputFormat));

                    $variantContents = file_get_contents($tmpOutput);
                    if ($variantContents !== false) {
                        $variantPath = $dir . $basename . '_' . $key . '.' . $outputFormat;

                        if ($storage->put($variantPath, $variantContents)) {
                            $variantSize = @getimagesize($tmpOutput);
                            $variants[$key] = [
                                'path'   => $variantPath,
                                'url'    => $storage->url($variantPath),
                                'width'  => $variantSize !== false ? $variantSize[0] : $target['width'],
                                'height' => $variantSize !== false ? $variantSize[1] : $target['height'],
                                'bytes'  => strlen($variantContents),
                                'mime_type' => $this->mimeTypeFor($outputFormat),
                            ];
                        }
                    }
                } finally {
                    if (file_exists($tmpOutput)) {
                        @unlink($tmpOutput);
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ImageVariantProcessor::generate failed: ' . $e->getMessage());
            $this->deleteVariants($variants, $storage);
            return ['variants' => [], 'dimensions' => $originalDimensions];
        } finally {
            if ($tmpOriginal !== null && file_exists($tmpOriginal)) {
                @unlink($tmpOriginal);
            }
        }

        return ['variants' => $variants, 'dimensions' => $originalDimensions];
    }

    /**
     * @param array{width: int|null, height: int|null} $originalDimensions
     * @return array{width: int, height: int}
     */
    private function targetDimensions(array $originalDimensions, int $targetWidth): array
    {
        $originalWidth  = (int) ($originalDimensions['width'] ?? 0);
        $originalHeight = (int) ($originalDimensions['height'] ?? 0);
        if ($originalWidth <= 0 || $originalHeight <= 0) {
            return ['width' => $targetWidth, 'height' => $targetWidth];
        }

        if ($originalWidth <= $targetWidth) {
            return ['width' => $originalWidth, 'height' => $originalHeight];
        }

        $ratio   = $originalHeight / $originalWidth;
        $height  = max(1, (int) round($targetWidth * $ratio));
        return ['width' => $targetWidth, 'height' => $height];
    }

    private function outputFormatFor(string $extension): string
    {
        return 'webp';
    }

    private function qualityFor(string $format): int
    {
        return match (strtolower($format)) {
            'webp' => self::WEBP_QUALITY,
            'jpg', 'jpeg' => self::JPEG_QUALITY,
            'png' => self::PNG_QUALITY,
            default => self::WEBP_QUALITY,
        };
    }

    private function mimeTypeFor(string $format): string
    {
        return match (strtolower($format)) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param array<string, array{path?: string}> $variants
     */
    public function deleteVariants(array $variants, StorageManager $storage): void
    {
        foreach ($variants as $variant) {
            if (is_array($variant) && isset($variant['path']) && $variant['path'] !== '') {
                $storage->delete($variant['path']);
            }
        }
    }
}
