<?php

use App\Services\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** A photo-like JPEG (noise, saved at top quality) so compression has something to gain. */
function photoUpload(int $width, int $height): UploadedFile
{
    $noise = imagecreatetruecolor(intdiv($width, 10), intdiv($height, 10));
    for ($x = 0; $x < imagesx($noise); $x++) {
        for ($y = 0; $y < imagesy($noise); $y++) {
            imagesetpixel($noise, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }
    $photo = imagescale($noise, $width, $height, IMG_BILINEAR_FIXED);

    $path = tempnam(sys_get_temp_dir(), 'photo').'.jpg';
    imagejpeg($photo, $path, 100);

    return new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);
}

test('a large photo is scaled down and stored as a smaller jpeg', function () {
    Storage::fake('uploads');
    $upload = photoUpload(3000, 2000);

    $path = app(ImageCompressor::class)->store($upload, 'foto_temuan');

    Storage::disk('uploads')->assertExists($path);
    [$width, $height] = getimagesizefromstring(Storage::disk('uploads')->get($path));

    expect($path)->toStartWith('foto_temuan/')->toEndWith('.jpg')
        ->and([$width, $height])->toBe([1600, 1067])
        ->and(Storage::disk('uploads')->size($path))->toBeLessThan($upload->getSize());
});

test('a small photo keeps its size', function () {
    $jpeg = app(ImageCompressor::class)->compress(photoUpload(800, 600));

    expect(getimagesizefromstring($jpeg)[0])->toBe(800);
});

test('a file that is not an image is stored unchanged', function () {
    Storage::fake('uploads');
    $upload = UploadedFile::fake()->createWithContent('foto.jpg', 'bukan gambar');

    $path = app(ImageCompressor::class)->store($upload, 'foto_temuan');

    expect(Storage::disk('uploads')->get($path))->toBe('bukan gambar');
});
