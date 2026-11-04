<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\FindingPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonConformity extends Model
{
    /** @use HasFactory<\Database\Factories\NonConformityFactory> */
    use HasFactory;

    protected $fillable = [
        'tanggal',
        'pabrik',
        'departemen',
        'deskripsi_temuan',
        'no_klausul',
        'kategori',
        'pic',
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

    /**
     * @return HasMany<FindingPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FindingPhoto::class, 'non_conformity_id')->orderBy('id');
    }

    /**
     * Photos of one field, oldest first.
     *
     * @return Collection<int, FindingPhoto>
     */
    public function photosOf(string $field): Collection
    {
        return $this->photos->where('field', $field)->values();
    }

    public function isClosed(): bool
    {
        return $this->status;
    }

    /** Badge colour of the category. */
    public function categoryColor(): string
    {
        return match ($this->kategori) {
            'Kritis' => 'error',
            'Major' => 'warning',
            'Minor' => 'info',
            default => 'gray',
        };
    }

    /**
     * What the photo viewer modal shows: the photos per field and the key facts of the finding.
     *
     * @return array<string, mixed>
     */
    public function photoViewerData(bool $canManage): array
    {
        $row = fn (string $label, ?string $value, ?string $badge = null) => [
            'label' => __($label),
            'value' => $value ?: '—',
            'badge' => $value ? $badge : null,
        ];

        return [
            'id' => $this->id,
            // auditees may only remove repair photos, like they may only add those
            'canDelete' => ['foto_temuan' => $canManage, 'foto_perbaikan' => true],
            'photos' => collect(FindingPhotos::FIELDS)->mapWithKeys(fn (string $field) => [
                $field => $this->photosOf($field)
                    ->map(fn (FindingPhoto $photo) => ['id' => $photo->id, 'url' => asset($photo->path)])
                    ->all(),
            ])->all(),
            'details' => [
                $row('nc.columns.date', $this->tanggal->translatedFormat('d M Y')),
                $row('nc.columns.plant', $this->plantName()),
                $row('nc.columns.department', $this->departmentName()),
                $row('nc.columns.finding', $this->deskripsi_temuan),
                $row('nc.columns.clause', $this->no_klausul),
                $row('nc.columns.category', $this->kategori, $this->categoryColor()),
                $row('nc.columns.pic', $this->picUser?->name ?? $this->pic),
                $row('nc.columns.status', __($this->isClosed() ? 'nc.closed' : 'nc.open'), $this->isClosed() ? 'success' : 'warning'),
            ],
        ];
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

    /**
     * Findings a user may see: those of their plants, and for an auditee only those of
     * their own departments plus any finding they are the PIC of.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->inPlants($user->accessiblePlants());

        if ($user->otorisasi === Role::Auditee) {
            $query->where(fn (Builder $query) => $query
                ->whereIn('departemen', $user->departmentCodes())
                ->orWhere('pic', $user->email));
        }
    }
}
