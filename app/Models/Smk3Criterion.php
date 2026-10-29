<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Smk3Criterion extends Model
{
    /** Category flag columns, in display order. */
    public const CATEGORIES = ['minor' => 'Minor', 'major' => 'Major', 'kritis' => 'Kritis'];

    /** Clause numbers look like 1.1 or 12.3.1. */
    public const NUMBER_PATTERN = '/^\d+(\.\d+)+$/';

    protected $table = 'smk3_kriteria';

    protected $fillable = [
        'nomor',
        'no_kriteria',
        'kriteria',
        'minor',
        'major',
        'kritis',
    ];

    protected function casts(): array
    {
        return [
            'minor' => 'boolean',
            'major' => 'boolean',
            'kritis' => 'boolean',
        ];
    }

    /**
     * All criteria sorted by clause number (1.2.1 before 1.10.1).
     *
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::all()->sortBy('no_kriteria', SORT_NATURAL)->values();
    }

    /** Criteria of one SMK3 element (clause numbers starting with "<element>."). */
    public function scopeInElement(Builder $query, int $element): void
    {
        $query->where('no_kriteria', 'like', "{$element}.%");
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where(function (Builder $query) use ($term) {
            $query->where('no_kriteria', 'like', "%{$term}%")->orWhere('kriteria', 'like', "%{$term}%");
        }));
    }

    /**
     * Number of criteria per element, optionally only those matching a search.
     *
     * @return array<int, int>
     */
    public static function countPerElement(?string $term = null): array
    {
        return static::query()->search($term)->pluck('no_kriteria')
            ->countBy(fn (string $number) => (int) strtok($number, '.'))
            ->all();
    }

    /** SMK3 element number, taken from the first part of the clause number. */
    public function element(): int
    {
        return (int) strtok($this->no_kriteria, '.');
    }

    /**
     * Finding categories (config smk3.categories) allowed for this criterion.
     *
     * @return list<string>
     */
    public function allowedCategories(): array
    {
        return array_values(array_filter(
            self::CATEGORIES,
            fn (string $label, string $column) => $this->{$column},
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Number of criteria in total and per category, for the page summary.
     *
     * @return array{total: int, minor: int, major: int, kritis: int}
     */
    public static function summary(): array
    {
        // toBase(): the model would cast the sums back to booleans (166 -> true -> 1)
        $row = static::query()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when minor then 1 else 0 end) as minor')
            ->selectRaw('sum(case when major then 1 else 0 end) as major')
            ->selectRaw('sum(case when kritis then 1 else 0 end) as kritis')
            ->first();

        return array_map('intval', (array) $row);
    }

    /** How many findings refer to this criterion. */
    public function findingsCount(): int
    {
        return NonConformity::where('no_klausul', $this->no_kriteria)->count();
    }
}
