<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\NonConformity;
use App\Models\User;
use App\Services\ImageCompressor;
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
            ->visibleTo($request->user())
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
            'allDepartments' => $this->departmentFilterOptions($request->user(), $plants),
            'auditees' => $this->auditees($plants),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }

    public function store(Request $request, ImageCompressor $images): RedirectResponse
    {
        $plants = $request->user()->accessiblePlants();
        $plant = $request->input('pabrik');

        $validated = $request->validateWithBag('createFinding', [
            'tanggal' => ['required', 'date'],
            'pabrik' => ['required', Rule::in($plants)],
            'departemen' => ['required', Rule::in(array_keys(NonConformity::departmentsFor(is_string($plant) ? $plant : null)))],
            'deskripsi_temuan' => ['required', 'string', 'max:2000'],
            // photos are compressed before storing, so a full-size phone photo is fine
            'foto_temuan' => ['nullable', 'image', 'max:10240', 'dimensions:max_width=10000,max_height=10000'],
            'no_klausul' => ['required', 'string', 'max:50'],
            'kategori' => ['required', Rule::in(config('smk3.categories'))],
            'pic' => [
                'required', 'email', Rule::exists('users', 'email')->where('otorisasi', Role::Auditee->value),
                // the PIC must belong to the finding's plant and department, or they could not see it
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $auditee = User::where('email', $value)->where('otorisasi', Role::Auditee)->first();
                    if ($auditee && (! in_array($request->input('pabrik'), $auditee->plantCodes(), true)
                        || ! in_array($request->input('departemen'), $auditee->departmentCodes(), true))) {
                        $fail(__('nc.create.picOutsideDepartment'));
                    }
                },
            ],
        ]);

        if ($request->hasFile('foto_temuan')) {
            $validated['foto_temuan'] = 'uploads/'.$images->store($request->file('foto_temuan'), 'foto_temuan');
        }

        NonConformity::create($validated);

        return redirect()->route('non-conformities')->with('success', __('nc.created'));
    }

    /**
     * Departments per plant for the filter; an auditee only gets their own departments.
     *
     * @param  list<string>  $plants
     * @return array<string, array<string, string>>
     */
    private function departmentFilterOptions(User $user, array $plants): array
    {
        return collect($plants)->mapWithKeys(function (string $plant) use ($user) {
            $departments = NonConformity::departmentsFor($plant);

            if ($user->otorisasi === Role::Auditee) {
                $departments = array_intersect_key($departments, array_flip($user->departmentCodes()));
            }

            return [$plant => $departments];
        })->all();
    }

    /**
     * Auditees that can be picked as PIC, with the plants of theirs this user can see and their departments.
     *
     * @param  list<string>  $plants
     * @return list<array{name: string, email: string, plants: list<string>, departments: list<string>}>
     */
    private function auditees(array $plants): array
    {
        return User::query()
            ->where('otorisasi', Role::Auditee)
            ->orderBy('name')
            ->get(['name', 'email', 'pabrik', 'departemen'])
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'plants' => array_values(array_intersect($plants, $user->plantCodes())),
                'departments' => $user->departmentCodes(),
            ])
            ->filter(fn (array $auditee) => $auditee['plants'] !== [])
            ->values()
            ->all();
    }
}
