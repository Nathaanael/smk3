<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonConformity extends Model
{
    /** @use HasFactory<\Database\Factories\NonConformityFactory> */
    use HasFactory;

    protected $fillable = [
        'tanggal',
        'pabrik',
        'departemen',
        'deskripsi_temuan',
        'foto_temuan',
        'no_klausul',
        'kategori',
        'pic',
        'foto_perbaikan',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => 'boolean',
        ];
    }

    /**
     * The auditee in charge; `pic` holds the user's email (as in the legacy system).
     */
    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic', 'email');
    }

    public function isClosed(): bool
    {
        return $this->status;
    }

    /**
     * Department display name, resolved against the department list of this row's plant.
     */
    public function departmentName(): string
    {
        return self::departmentsFor($this->pabrik)[$this->departemen] ?? $this->departemen;
    }

    public function plantName(): string
    {
        return config('smk3.plants')[$this->pabrik] ?? strtoupper($this->pabrik);
    }

    /**
     * @return array<string, string>
     */
    public static function departmentsFor(?string $plant): array
    {
        $departments = config('smk3.departments');

        return $departments[$plant] ?? $departments['default'];
    }

    /**
     * @param  list<string>  $plants
     */
    public function scopeInPlants(Builder $query, array $plants): void
    {
        $query->whereIn('pabrik', $plants);
    }
}
