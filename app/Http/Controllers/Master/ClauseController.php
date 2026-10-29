<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Services\Smk3CriterionSpreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClauseController extends Controller
{
    /**
     * One tab per SMK3 element; only the criteria of the active tab are loaded.
     */
    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'element' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['q'] ?? null;

        $counts = Smk3Criterion::countPerElement($search);
        // config elements first, then any element only present in the data
        $elements = config('smk3.elements') + array_fill_keys(array_keys($counts), null);
        ksort($elements);

        // while searching, jump to the first element with a match if the current one has none
        $active = (int) ($filters['element'] ?? array_key_first($elements));
        if ($search && empty($counts[$active]) && $counts !== []) {
            $active = array_key_first(array_filter($elements, fn ($name, $element) => ! empty($counts[$element]), ARRAY_FILTER_USE_BOTH));
        }

        $criteria = Smk3Criterion::query()->inElement($active)->search($search)->get()
            ->sortBy('no_kriteria', SORT_NATURAL)->values();

        return view('pages.master.clauses', [
            'title' => __('master.clauses'),
            'criteria' => $criteria,
            'elements' => $elements,
            'counts' => $counts,
            'active' => $active,
            'search' => $search,
            'summary' => Smk3Criterion::summary(),
        ])->fragmentIf($request->header('X-Partial') === 'results', 'results');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCriterion($request);

        Smk3Criterion::create($validated + ['nomor' => (int) Smk3Criterion::max('nomor') + 1]);

        return $this->toElement((int) strtok($validated['no_kriteria'], '.'))->with('success', __('clause.created', ['number' => $validated['no_kriteria']]));
    }

    public function update(Request $request, Smk3Criterion $criterion): RedirectResponse
    {
        $validated = $this->validateCriterion($request, $criterion);

        DB::transaction(function () use ($criterion, $validated) {
            // findings store the clause number as text, so a renumbered clause takes them along
            if ($validated['no_kriteria'] !== $criterion->no_kriteria) {
                NonConformity::where('no_klausul', $criterion->no_kriteria)->update(['no_klausul' => $validated['no_kriteria']]);
            }

            $criterion->update($validated);
        });

        return $this->toElement($criterion->element())->with('success', __('clause.updated', ['number' => $criterion->no_kriteria]));
    }

    public function destroy(Smk3Criterion $criterion): RedirectResponse
    {
        if ($used = $criterion->findingsCount()) {
            return $this->toElement($criterion->element())->with('error', __('clause.inUse', ['number' => $criterion->no_kriteria, 'count' => $used]));
        }

        $criterion->delete();

        return $this->toElement($criterion->element())->with('success', __('clause.deleted', ['number' => $criterion->no_kriteria]));
    }

    /**
     * Save the category checkboxes changed in the table, all at once.
     */
    public function saveCategories(Request $request): JsonResponse
    {
        $rules = [
            'changes' => ['required', 'array', 'min:1', 'max:500'],
            'changes.*.id' => ['required', 'integer', 'distinct', 'exists:smk3_kriteria,id'],
        ];
        foreach (array_keys(Smk3Criterion::CATEGORIES) as $column) {
            $rules["changes.*.{$column}"] = ['required', 'boolean'];
        }
        $changes = $request->validate($rules)['changes'];

        DB::transaction(function () use ($changes) {
            $criteria = Smk3Criterion::findMany(array_column($changes, 'id'))->keyBy('id');

            foreach ($changes as $change) {
                $criteria[$change['id']]->update(array_map(
                    fn ($value) => (bool) $value,
                    array_intersect_key($change, Smk3Criterion::CATEGORIES),
                ));
            }
        });

        return response()->json([
            'message' => __('clause.categoriesSaved', ['count' => count($changes)]),
            'summary' => Smk3Criterion::summary(),
        ]);
    }

    public function template(Smk3CriterionSpreadsheet $spreadsheet): StreamedResponse
    {
        $workbook = $spreadsheet->template(Smk3Criterion::ordered());

        return response()->streamDownload(function () use ($workbook) {
            IOFactory::createWriter($workbook, 'Xlsx')->save('php://output');
        }, 'template-klausul-smk3.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request, Smk3CriterionSpreadsheet $spreadsheet): RedirectResponse
    {
        $request->validateWithBag('import', [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:2048'],
        ]);

        ['rows' => $rows, 'errors' => $errors] = $spreadsheet->read($request->file('file'));

        // nothing is saved unless the whole file is valid
        if ($errors !== []) {
            return redirect()->route('master.clauses')->with('importErrors', $errors);
        }

        $result = $spreadsheet->import($rows);

        return redirect()->route('master.clauses')->with('success', __('clause.import.done', $result));
    }

    /** Back to the list, on the tab of the given element. */
    private function toElement(int $element): RedirectResponse
    {
        return redirect()->route('master.clauses', ['element' => $element]);
    }

    /**
     * @return array{no_kriteria: string, kriteria: string, minor: bool, major: bool, kritis: bool}
     */
    private function validateCriterion(Request $request, ?Smk3Criterion $criterion = null): array
    {
        $validated = $request->validateWithBag('clause', [
            'no_kriteria' => ['required', 'string', 'max:20', 'regex:'.Smk3Criterion::NUMBER_PATTERN, Rule::unique('smk3_kriteria', 'no_kriteria')->ignore($criterion)],
            'kriteria' => ['required', 'string', 'max:2000'],
            'minor' => ['boolean'],
            'major' => ['boolean'],
            'kritis' => ['boolean'],
        ]);

        // unchecked boxes are not submitted
        foreach (array_keys(Smk3Criterion::CATEGORIES) as $column) {
            $validated[$column] = (bool) ($validated[$column] ?? false);
        }

        return $validated;
    }
}
