<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\FindingFilterRequest;
use App\Services\FindingExporter;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use App\Services\ClauseSuggester;
use App\Services\FindingPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NonConfirmitiesController extends Controller
{
    public function index(FindingFilterRequest $request): View|string
    {
        $plants = $request->user()->accessiblePlants();
        $filters = $request->validated();

        $findings = $request->findings()
            ->paginate($filters['per_page'] ?? FindingFilterRequest::PER_PAGE_OPTIONS[0])
            ->withQueryString();

        // AJAX search/filter/pagination only needs the results part of the page
        return view('pages.nonConfirmities.index', [
            'title' => __('smk3.nonConformities'),
            'findings' => $findings,
            'filters' => $filters,
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'categories' => config('smk3.categories'),
            'perPageOptions' => FindingFilterRequest::PER_PAGE_OPTIONS,
            'canManage' => in_array($request->user()->otorisasi, [Role::Admin, Role::Auditor], true),
            'exportColumns' => FindingExporter::columns(),
            'allDepartments' => $this->departmentFilterOptions($request->user(), $plants),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }

    /**
     * Full-page "Add finding" form. After "Save & add another" the date, plant and
     * department of the previous finding are kept (session "keep").
     */
    public function create(Request $request): View
    {
        return view('pages.nonConfirmities.create', $this->formData($request) + [
            'title' => __('nc.create.title'),
            'finding' => null,
            'keep' => session('keep', []),
        ]);
    }

    public function store(Request $request, FindingPhotos $photos): RedirectResponse
    {
        $validated = $request->validate($this->rules($request));

        $finding = NonConformity::create(collect($validated)->except('foto_temuan')->all());
        if ($request->hasFile('foto_temuan')) {
            $photos->replace($finding, 'foto_temuan', $request->file('foto_temuan'));
        }

        // "Save & add another": back to an empty form for the next finding of the same audit round
        if ($request->boolean('another')) {
            return redirect()->route('non-conformities.create')
                ->with('success', __('nc.created'))
                ->with('keep', collect($validated)->only(['tanggal', 'pabrik', 'departemen'])->all());
        }

        return redirect()->route('non-conformities')->with('success', __('nc.created'));
    }

    public function edit(Request $request, NonConformity $finding): View
    {
        $this->ensureVisible($request->user(), $finding);

        return view('pages.nonConfirmities.create', $this->formData($request) + [
            'title' => __('nc.edit.title', ['id' => $finding->id]),
            'finding' => $finding,
            'keep' => [],
        ]);
    }

    public function update(Request $request, NonConformity $finding, FindingPhotos $photos): RedirectResponse
    {
        $this->ensureVisible($request->user(), $finding);

        $validated = $request->validate($this->rules($request) + [
            'status' => ['required', 'boolean'],
            'foto_perbaikan' => ['nullable', 'image', 'max:10240', 'dimensions:max_width=10000,max_height=10000'],
        ]);

        $finding->update(collect($validated)->except(FindingPhotos::FIELDS)->all());
        foreach (FindingPhotos::FIELDS as $field) {
            if ($request->hasFile($field)) {
                $photos->replace($finding, $field, $request->file($field));
            }
        }

        return redirect()->route('non-conformities')->with('success', __('nc.updated', ['id' => $finding->id]));
    }

    public function destroy(Request $request, NonConformity $finding, FindingPhotos $photos): JsonResponse
    {
        $this->ensureVisible($request->user(), $finding);

        $photos->deleteAll($finding);
        $finding->delete();

        return response()->json(['message' => __('nc.deleted', ['count' => 1])]);
    }

    /**
     * Shared data of the add / edit form.
     *
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $plants = $request->user()->accessiblePlants();

        return [
            'plants' => array_intersect_key(config('smk3.plants'), array_flip($plants)),
            'departments' => collect($plants)->mapWithKeys(fn (string $code) => [$code => NonConformity::departmentsFor($code)])->all(),
            'categories' => config('smk3.categories'),
            'auditees' => $this->auditees($plants),
            'clauses' => Smk3Criterion::ordered()->mapWithKeys(fn (Smk3Criterion $criterion) => [
                $criterion->no_kriteria => ['text' => $criterion->kriteria, 'categories' => $criterion->allowedCategories()],
            ])->all(),
        ];
    }

    /** Findings outside the user's plants (or an auditee's departments) do not exist for them. */
    private function ensureVisible(User $user, NonConformity $finding): void
    {
        abort_unless(NonConformity::visibleTo($user)->whereKey($finding->getKey())->exists(), 404);
    }

    /**
     * Validation rules of the add / edit form.
     *
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        $plants = $request->user()->accessiblePlants();
        $plant = $request->input('pabrik');
        $criterion = Smk3Criterion::where('no_kriteria', (string) $request->input('no_klausul'))->first();

        return [
            'tanggal' => ['required', 'date'],
            'pabrik' => ['required', Rule::in($plants)],
            'departemen' => ['required', Rule::in(array_keys(NonConformity::departmentsFor(is_string($plant) ? $plant : null)))],
            'deskripsi_temuan' => ['required', 'string', 'max:2000'],
            // photos are compressed before storing, so a full-size phone photo is fine
            'foto_temuan' => ['nullable', 'image', 'max:10240', 'dimensions:max_width=10000,max_height=10000'],
            'no_klausul' => ['required', 'string', 'max:50', Rule::exists('smk3_kriteria', 'no_kriteria')],
            'kategori' => [
                'required', Rule::in(config('smk3.categories')),
                // only the categories ticked for the clause in the clause master
                function (string $attribute, mixed $value, \Closure $fail) use ($criterion) {
                    if ($criterion && ! in_array($value, $criterion->allowedCategories(), true)) {
                        $fail(__('nc.create.categoryNotAllowed', ['category' => $value, 'clause' => $criterion->no_kriteria]));
                    }
                },
            ],
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
        ];
    }

    /**
     * Clauses related to a finding description, for the "Add finding" form.
     */
    public function suggestClauses(Request $request, ClauseSuggester $suggester): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:2000']]);

        $suggestions = array_map(fn (array $suggestion) => [
            'no' => $suggestion['no'],
            'text' => $suggestion['text'],
            'reason' => implode(' · ', array_filter([
                $suggestion['reasons']['keywords'] ? __('nc.suggest.keyword', ['words' => implode(', ', $suggestion['reasons']['keywords'])]) : null,
                $suggestion['reasons']['similar'] ? trans_choice('nc.suggest.similar', $suggestion['reasons']['similar'], ['count' => $suggestion['reasons']['similar']]) : null,
                ! $suggestion['reasons']['keywords'] && ! $suggestion['reasons']['similar'] ? __('nc.suggest.criterion') : null,
            ])),
        ], $suggester->suggest((string) ($validated['q'] ?? '')));

        return response()->json(['suggestions' => $suggestions]);
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
