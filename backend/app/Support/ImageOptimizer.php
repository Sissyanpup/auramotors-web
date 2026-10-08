<?php

namespace App\Support;

/**
 * Downscales and re-compresses an uploaded image in place (GD, bundled with
 * PHP — no extra dependency) so vehicle listings with 10-20 photos don't
 * ship full-resolution camera/phone photos to every catalog visitor.
 */
class ImageOptimizer
{
    public static function compress(string $absolutePath, int $maxWidth = 1600, int $jpegQuality = 78): void
    {
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            return;
        }

        [$width, $height, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if ($source === false) {
            return;
        }

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $source = $resized;
        }

        match ($type) {
            IMAGETYPE_PNG => imagepng($source, $absolutePath, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($source, $absolutePath, $jpegQuality) : imagejpeg($source, $absolutePath, $jpegQuality),
            default => imagejpeg($source, $absolutePath, $jpegQuality),
        };
    }
}
