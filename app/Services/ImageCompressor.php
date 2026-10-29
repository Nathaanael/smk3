<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks uploaded photos before they are stored: the longest side is scaled down to
 * MAX_SIDE pixels and the image is saved as JPEG. Phone photos (3–8 MB) end up at a
 * few hundred KB, still sharp enough to read an audit finding. Uses PHP's GD extension.
 */
class ImageCompressor
{
    public const MAX_SIDE = 1600;

    public const QUALITY = 75;

    /**
     * Store the compressed photo on the disk and return its path on that disk.
     * Falls back to the original file when it cannot be decoded or would not get smaller.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'uploads'): string
    {
        $jpeg = $this->compress($file);

        if ($jpeg === null || strlen($jpeg) >= $file->getSize()) {
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.jpg';
        Storage::disk($disk)->put($path, $jpeg);

        return $path;
    }

    /** JPEG bytes of the scaled-down photo, or null when GD cannot read it. */
    public function compress(UploadedFile $file): ?string
    {
        $contents = file_get_contents($file->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if (! $source instanceof GdImage) {
            return null;
        }

        $source = $this->orient($source, $file);

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // white background, so transparent PNG/GIF areas do not turn black in the JPEG
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imageinterlace($target, true);
        imagejpeg($target, null, self::QUALITY);

        return ob_get_clean() ?: null;
    }

    /**
     * Small JPEG copy of a photo under public/ (e.g. "uploads/foto_temuan/x.jpg") for reports,
     * cached in storage/app/thumbnails. Returns its absolute path, or null when the photo is missing
     * or unreadable.
     */
    public function thumbnail(?string $publicPath, int $maxSide = 360): ?string
    {
        $source = $publicPath ? public_path($publicPath) : null;
        if (! $source || ! is_file($source)) {
            return null;
        }

        $target = storage_path('app/thumbnails/'.md5($publicPath.'|'.filemtime($source).'|'.$maxSide).'.jpg');
        if (is_file($target)) {
            return $target;
        }

        $image = @imagecreatefromstring((string) file_get_contents($source));
        if (! $image instanceof GdImage) {
            return null;
        }

        $scale = min(1, $maxSide / max(imagesx($image), imagesy($image)));
        $width = max(1, (int) round(imagesx($image) * $scale));
        $height = max(1, (int) round(imagesy($image) * $scale));

        $thumb = imagecreatetruecolor($width, $height);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        @mkdir(dirname($target), 0775, true);
        imagejpeg($thumb, $target, 70);

        return $target;
    }

    /**
     * PNG copy of any image GD can read (e.g. the .webp logo), cached in storage/app/thumbnails,
     * for renderers that do not read every format.
     */
    public function png(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $target = storage_path('app/thumbnails/'.md5($absolutePath.'|'.filemtime($absolutePath)).'.png');
        if (is_file($target)) {
            return $target;
        }

        $image = @imagecreatefromstring((string) file_get_contents($absolutePath));
        if (! $image instanceof GdImage) {
            return null;
        }

        imagesavealpha($image, true);
        @mkdir(dirname($target), 0775, true);
        imagepng($image, $target);

        return $target;
    }

    /** Phone cameras store rotation in EXIF instead of rotating the pixels; apply it, since the JPEG drops EXIF. */
    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
