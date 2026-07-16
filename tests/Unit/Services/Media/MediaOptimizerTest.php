<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Services\Media\MediaOptimizer;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

function createTestImage(int $width, int $height, string $format = 'image/jpeg', string $fill = 'cccccc'): string
{
    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage($width, $height)->fill($fill);
    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
    $encoded = $image->encodeUsingMediaType($format);
    file_put_contents($tempFile, (string) $encoded);

    return $tempFile;
}

/**
 * A valid PNG header declaring huge dimensions with no pixel data: getimagesize
 * reads the size cheaply, so the memory-budget guard fires without allocating a
 * multi-gigabyte GD buffer.
 */
function createHugeHeaderImage(int $width, int $height): string
{
    $ihdr = pack('N', $width).pack('N', $height)."\x08\x02\x00\x00\x00";
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', 0);
    $tempFile = tempnam(sys_get_temp_dir(), 'huge_hdr_');
    file_put_contents($tempFile, $png);

    return $tempFile;
}

$tempFiles = [];

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $file) {
        @unlink($file);
    }
    $tempFiles = [];
});

it('resizes wide image for instagram', function () use (&$tempFiles) {
    $source = createTestImage(3000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1440);

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for bluesky under 1mb', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Bluesky);
    $tempFiles[] = $result;

    expect(filesize($result))->toBeLessThan(976 * 1024);
});

it('does not upscale small images', function () use (&$tempFiles) {
    $source = createTestImage(500, 500);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBe(500);
});

it('converts png to jpeg for instagram', function () use (&$tempFiles) {
    $source = createTestImage(800, 600, 'image/png');
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for tiktok max 1080', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::TikTok);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1080);
});

it('resizes for pinterest max 1000', function () use (&$tempFiles) {
    $source = createTestImage(2000, 3000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Pinterest);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1000);
});

it('exposes the configured max width per platform', function () {
    $optimizer = new MediaOptimizer;

    expect($optimizer->maxWidthForPlatform(Platform::TikTok))->toBe(1080)
        ->and($optimizer->maxWidthForPlatform(Platform::Instagram))->toBe(1440)
        ->and($optimizer->maxWidthForPlatform(Platform::Pinterest))->toBe(1000);
});

it('reports a max width for every platform', function () {
    $optimizer = new MediaOptimizer;

    foreach (Platform::cases() as $platform) {
        expect($optimizer->maxWidthForPlatform($platform))->toBeInt()->toBeGreaterThan(0);
    }
});

it('handles all platforms without error', function () use (&$tempFiles) {
    $source = createTestImage(1000, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;

    foreach (Platform::cases() as $platform) {
        $result = $optimizer->optimizeImage($source, $platform);
        $tempFiles[] = $result;

        expect(file_exists($result))->toBeTrue()
            ->and(filesize($result))->toBeGreaterThan(0);
    }
});

it('returns valid temp file path', function () use (&$tempFiles) {
    $source = createTestImage(800, 600);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Facebook);
    $tempFiles[] = $result;

    expect(file_exists($result))->toBeTrue()
        ->and(filesize($result))->toBeGreaterThan(0);
});

it('crops a wide image to a 1:1 square', function () use (&$tempFiles) {
    $source = createTestImage(1920, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe($cropped->height());
    expect($cropped->height())->toBe(1080);
});

it('crops a tall image to a 4:5 portrait', function () use (&$tempFiles) {
    $source = createTestImage(1000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 4 / 5);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 0.8))->toBeLessThan(0.01);
});

it('crops a square image to a 16:9 landscape', function () use (&$tempFiles) {
    $source = createTestImage(1080, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 16 / 9);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 16 / 9))->toBeLessThan(0.01);
});

it('returns a copy when image already matches the target ratio', function () use (&$tempFiles) {
    $source = createTestImage(800, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe(800);
    expect($cropped->height())->toBe(800);
});

it('fills the gaps with a darkened image-derived background, never a black letterbox', function () use (&$tempFiles) {
    $source = createTestImage(1200, 900, 'image/jpeg', 'ff0000'); // 4:3 red, wider than 9:16
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->fitToCanvas($source, 1080, 1920);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $out = $manager->decodePath($result);

    expect($out->width())->toBe(1080)
        ->and($out->height())->toBe(1920);

    // A 4:3 image is letterboxed top & bottom on a 9:16 canvas. The band must be
    // a darkened copy of the red image, not a black bar, and darker than the
    // centered foreground on top of it.
    $band = $out->colorAt(540, 40);        // top background band
    $foreground = $out->colorAt(540, 960); // centered image

    expect($band->red()->value())->toBeGreaterThan(120)
        ->and($band->red()->value())->toBeGreaterThan($band->green()->value() + 60)
        ->and($foreground->red()->value())->toBeGreaterThan($band->red()->value());
});

it('scales an already-9:16 image down to the canvas with no letterbox band', function () use (&$tempFiles) {
    $source = createTestImage(2160, 3840, 'image/jpeg', 'ff0000'); // already 9:16, larger than canvas
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->fitToCanvas($source, 1080, 1920);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $out = $manager->decodePath($result);

    // Scaled down to the exact canvas, and no darkened band was added: a corner
    // (background region for the fit path) matches the centre.
    $corner = $out->colorAt(0, 0);
    $center = $out->colorAt(540, 960);

    expect($out->width())->toBe(1080)
        ->and($out->height())->toBe(1920)
        ->and(abs($corner->red()->value() - $center->red()->value()))->toBeLessThan(10);
});

it('throws when the source bytes are not a decodable image', function () use (&$tempFiles) {
    $bad = tempnam(sys_get_temp_dir(), 'bad_fit_');
    file_put_contents($bad, '<html>404 not found</html>');
    $tempFiles[] = $bad;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->fitToCanvas($bad, 1080, 1920))->toThrow(Exception::class);
});

it('refuses to fit an image whose dimensions exceed the memory budget', function () use (&$tempFiles) {
    $huge = createHugeHeaderImage(20000, 20000);
    $tempFiles[] = $huge;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->fitToCanvas($huge, 1080, 1920))
        ->toThrow(RuntimeException::class, 'exceed the safe processing budget');
});

it('refuses to crop an image whose dimensions exceed the memory budget', function () use (&$tempFiles) {
    $huge = createHugeHeaderImage(20000, 20000);
    $tempFiles[] = $huge;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->cropToAspectRatio($huge, 0.8))
        ->toThrow(RuntimeException::class, 'exceed the safe processing budget');
});
