<?php

namespace App\Services;

use App\Models\NonConformity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Photo files of a finding (foto_temuan, foto_perbaikan). The columns hold
 * "uploads/<folder>/<file>", relative to public/ like the legacy system; the
 * files live on the "uploads" disk (public/uploads).
 */
class FindingPhotos
{
    public const FIELDS = ['foto_temuan', 'foto_perbaikan'];

    public function __construct(private ImageCompressor $images) {}

    /** Store a compressed photo in the field, removing the photo it replaces. */
    public function replace(NonConformity $finding, string $field, UploadedFile $file): void
    {
        $old = $finding->{$field};

        $finding->update([$field => 'uploads/'.$this->images->store($file, $field)]);

        // prepare the report thumbnail now, so exports with photos stay fast
        $this->images->thumbnail($finding->{$field});

        $this->deleteFile($old);
    }

    public function deleteAll(NonConformity $finding): void
    {
        foreach (self::FIELDS as $field) {
            $this->deleteFile($finding->{$field});
        }
    }

    private function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }
}
