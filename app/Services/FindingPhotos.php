<?php

namespace App\Services;

use App\Models\FindingPhoto;
use App\Models\NonConformity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Photo files of a finding (foto_temuan, foto_perbaikan). Each photo is a FindingPhoto row
 * holding "uploads/<folder>/<file>", relative to public/ like the legacy system; the files
 * live on the "uploads" disk (public/uploads).
 */
class FindingPhotos
{
    public const FIELDS = ['foto_temuan', 'foto_perbaikan'];

    /** Most photos one finding may hold per field; photos stored before this limit stay. */
    public const MAX_PER_FIELD = 5;

    public function __construct(private ImageCompressor $images) {}

    /**
     * Validation rules for uploading photos to a field, within the room left on the finding.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $input, ?NonConformity $finding = null, ?string $field = null): array
    {
        return [
            $input => ['nullable', 'array', 'max:'.self::room($finding, $field ?? $input)],
            // photos are compressed before storing, so a full-size phone photo is fine
            "{$input}.*" => ['image', 'max:10240', 'dimensions:max_width=10000,max_height=10000'],
        ];
    }

    /** How many more photos the field can take. */
    public static function room(?NonConformity $finding, string $field): int
    {
        $stored = $finding ? $finding->photos()->where('field', $field)->count() : 0;

        return max(0, self::MAX_PER_FIELD - $stored);
    }

    /**
     * Store compressed photos in the field, next to the ones it already holds.
     *
     * @param  list<UploadedFile>  $files
     */
    public function add(NonConformity $finding, string $field, array $files): void
    {
        foreach ($files as $file) {
            $path = 'uploads/'.$this->images->store($file, $field);
            $finding->photos()->create(['field' => $field, 'path' => $path]);

            // prepare the report thumbnail now, so exports with photos stay fast
            $this->images->thumbnail($path);
        }
    }

    public function delete(FindingPhoto $photo): void
    {
        $this->deleteFile($photo->path);
        $photo->delete();
    }

    /** Remove the files of a finding that is being deleted (its rows go with it). */
    public function deleteAll(NonConformity $finding): void
    {
        foreach ($finding->photos as $photo) {
            $this->deleteFile($photo->path);
        }
    }

    private function deleteFile(string $path): void
    {
        if (str_starts_with($path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($path, strlen('uploads/')));
        }
    }
}
