<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finding or repair photo. `field` is foto_temuan or foto_perbaikan; `path` is
 * "uploads/<folder>/<file>", relative to public/ like the legacy system.
 */
class FindingPhoto extends Model
{
    protected $table = 'non_conformity_photos';

    protected $fillable = ['field', 'path'];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(NonConformity::class, 'non_conformity_id');
    }
}
