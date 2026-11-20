<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Re-encodes every uploaded image with GD before it's stored.
 *
 * Decoding and re-encoding throws away anything that isn't pixels: EXIF data
 * (including GPS location), embedded scripts, and "polyglot" files that are
 * both a valid image and something else. The stored file always has a random
 * name and a .jpg extension we chose, never the uploader's filename.
 */
class ImageService
{
    /** Stores a post image, scaled down so its longest side is at most $maxDimension. */
    public function storePostImage(UploadedFile $file): string
    {
        $image = $this->decode($file);
        $image = $this->fitWithin($image, (int) config('aviate.uploads.post_image_max_dimension'));

        return $this->storeJpeg($image, 'posts');
    }

    /** Stores a square avatar, centre-cropped and resized to the configured size. */
    public function storeAvatar(UploadedFile $file): string
    {
        $image = $this->decode($file);
        $size = (int) config('aviate.uploads.avatar_size');

        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);

        $square = imagecreatetruecolor($size, $size);
        $this->fillWhite($square);
        imagecopyresampled($square, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);
        imagedestroy($image);

        return $this->storeJpeg($square, 'avatars');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function decode(UploadedFile $file): \GdImage
    {
        $contents = file_get_contents($file->getRealPath());
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof \GdImage) {
            throw ValidationException::withMessages(['image' => 'That file could not be read as an image.']);
        }

        return $this->applyExifOrientation($image, $file);
    }

    private function fitWithin(\GdImage $image, int $max): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);

        if ($w <= $max && $h <= $max) {
            return $image;
        }

        $scale = $max / max($w, $h);
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $resized = imagecreatetruecolor($newW, $newH);
        $this->fillWhite($resized);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);

        return $resized;
    }

    private function storeJpeg(\GdImage $image, string $directory): string
    {
        // Flatten transparency (PNG/WebP) onto white so it doesn't turn black as JPEG.
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        $this->fillWhite($flat);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);

        ob_start();
        imagejpeg($flat, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($flat);

        $path = $directory.'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function fillWhite(\GdImage $image): void
    {
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
    }

    /** Phone photos are often stored sideways with an EXIF "rotate me" flag; honour it before we strip EXIF. */
    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $rotation = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($rotation === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $rotation, 0);
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
