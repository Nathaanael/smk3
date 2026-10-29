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

    public const FILTERS = ['q', 'pabrik', 'departemen', 'kategori', 'status', 'dari', 'sampai'];

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
        ];
    }

    /**
     * Findings matching the filters, newest first.
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
            ->latest('tanggal')
            ->latest('id');
    }
}
