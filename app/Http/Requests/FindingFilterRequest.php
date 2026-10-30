<?php

namespace App\Http\Requests;

use App\Models\NonConformity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Search and filters of the findings list, shared by the list itself and its Excel / PDF export.
 * Every filter is optional: with none set, all findings the user may see are returned.
 */
class FindingFilterRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const FILTERS = ['q', 'pabrik', 'departemen', 'kategori', 'status', 'dari', 'sampai', 'sort', 'dir'];

    /** Columns the list can be sorted by (query value => database column). */
    public const SORTABLE = [
        'id' => 'id',
        'tanggal' => 'tanggal',
        'pabrik' => 'pabrik',
        'departemen' => 'departemen',
        'temuan' => 'deskripsi_temuan',
        'klausul' => 'no_klausul',
        'kategori' => 'kategori',
        'pic' => 'pic',
        'status' => 'status',
    ];

    /**
     * An unknown order (old bookmark, edited URL) falls back to the default order instead of
     * failing the whole list or export.
     */
    protected function prepareForValidation(): void
    {
        $sort = $this->input('sort');
        if ($sort !== null && ! (is_string($sort) && array_key_exists($sort, self::SORTABLE))) {
            $this->merge(['sort' => null, 'dir' => null]);
        }
        if (! in_array($this->input('dir'), [null, 'asc', 'desc'], true)) {
            $this->merge(['dir' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'pabrik' => ['nullable', Rule::in($this->user()->accessiblePlants())],
            'departemen' => ['nullable', 'string', 'max:50'],
            'kategori' => ['nullable', Rule::in(config('smk3.categories'))],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTABLE))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Findings matching the filters, in the chosen order (newest first by default).
     *
     * @return Builder<NonConformity>
     */
    public function findings(): Builder
    {
        $filters = $this->validated();

        return NonConformity::query()
            ->with('picUser:id,name,email')
            ->visibleTo($this->user())
            ->when($filters['pabrik'] ?? null, fn ($query, $plant) => $query->where('pabrik', $plant))
            ->when($filters['departemen'] ?? null, fn ($query, $department) => $query->where('departemen', $department))
            ->when($filters['kategori'] ?? null, fn ($query, $category) => $query->where('kategori', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status === 'closed'))
            ->when($filters['dari'] ?? null, fn ($query, $from) => $query->whereDate('tanggal', '>=', $from))
            ->when($filters['sampai'] ?? null, fn ($query, $to) => $query->whereDate('tanggal', '<=', $to))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('deskripsi_temuan', 'like', "%{$search}%")
                        ->orWhere('pic', 'like', "%{$search}%")
                        ->orWhere('no_klausul', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['sort'] ?? null,
                fn ($query, $sort) => $query->orderBy(self::SORTABLE[$sort], $filters['dir'] ?? 'asc')
                    ->when($sort !== 'id', fn ($query) => $query->latest('id')),
                fn ($query) => $query->latest('tanggal')->latest('id'),
            );
    }
}
