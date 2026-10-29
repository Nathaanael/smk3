<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NonConfirmitiesController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request): View|string
    {
        $plants = $request->user()->accessiblePlants();

        // every filter is optional: with none set the page lists all data the user may see
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'pabrik' => ['nullable', Rule::in($plants)],
            'departemen' => ['nullable', 'string', 'max:50'],
            'kategori' => ['nullable', Rule::in(config('smk3.categories'))],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $findings = NonConformity::query()
            ->with('picUser:id,name,email')
            ->inPlants($plants)
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
            ->latest('id')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString();

        // AJAX search/filter/pagination only needs the results part of the page
        return view('pages.nonConfirmities.index', [
            'title' => __('smk3.nonConformities'),
            'findings' => $findings,
            'filters' => $filters,
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'categories' => config('smk3.categories'),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'canCreate' => in_array($request->user()->otorisasi, [Role::Admin, Role::Auditor], true),
            'allDepartments' => collect($plants)->mapWithKeys(fn (string $code) => [$code => NonConformity::departmentsFor($code)])->all(),
            'auditees' => $this->auditees($plants),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }

    public function store(Request $request): RedirectResponse
    {
        $plants = $request->user()->accessiblePlants();
        $plant = $request->input('pabrik');

        $validated = $request->validateWithBag('createFinding', [
            'tanggal' => ['required', 'date'],
            'pabrik' => ['required', Rule::in($plants)],
            'departemen' => ['required', Rule::in(array_keys(NonConformity::departmentsFor(is_string($plant) ? $plant : null)))],
            'deskripsi_temuan' => ['required', 'string', 'max:2000'],
            'foto_temuan' => ['nullable', 'image', 'max:5120'],
            'no_klausul' => ['required', 'string', 'max:50'],
            'kategori' => ['required', Rule::in(config('smk3.categories'))],
            'pic' => ['required', 'email', Rule::exists('users', 'email')->where('otorisasi', Role::Auditee->value)],
        ]);

        if ($request->hasFile('foto_temuan')) {
            $validated['foto_temuan'] = 'uploads/'.$request->file('foto_temuan')->store('foto_temuan', 'uploads');
        }

        NonConformity::create($validated);

        return redirect()->route('non-conformities')->with('success', __('nc.created'));
    }

    /**
     * Auditees that can be picked as PIC, with the plants of theirs this user can see.
     *
     * @param  list<string>  $plants
     * @return list<array{name: string, email: string, plants: list<string>}>
     */
    private function auditees(array $plants): array
    {
        return User::query()
            ->where('otorisasi', Role::Auditee)
            ->orderBy('name')
            ->get(['name', 'email', 'pabrik'])
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'plants' => array_values(array_intersect($plants, array_map('trim', explode(',', (string) $user->pabrik)))),
            ])
            ->filter(fn (array $auditee) => $auditee['plants'] !== [])
            ->values()
            ->all();
    }
}
