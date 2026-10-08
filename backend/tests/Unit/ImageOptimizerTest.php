<?php

namespace Tests\Unit;

use App\Support\ImageOptimizer;
use PHPUnit\Framework\TestCase;

class ImageOptimizerTest extends TestCase
{
    public function test_it_downscales_an_image_wider_than_the_max_width(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        $image = imagecreatetruecolor(2000, 1000);
        imagejpeg($image, $path, 90);
        $originalSize = filesize($path);

        ImageOptimizer::compress($path, maxWidth: 800, jpegQuality: 60);

        [$width, $height] = getimagesize($path);

        $this->assertSame(800, $width);
        $this->assertSame(400, $height);
        $this->assertLessThan($originalSize, filesize($path));

        unlink($path);
    }

    public function test_it_leaves_an_image_narrower_than_the_max_width_untouched_in_size(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        $image = imagecreatetruecolor(200, 100);
        imagejpeg($image, $path, 90);

        ImageOptimizer::compress($path, maxWidth: 800, jpegQuality: 60);

        [$width, $height] = getimagesize($path);

        $this->assertSame(200, $width);
        $this->assertSame(100, $height);

        unlink($path);
    }
}
