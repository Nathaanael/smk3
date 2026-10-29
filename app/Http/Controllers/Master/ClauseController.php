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
    public function index(): View
    {
        $criteria = Smk3Criterion::ordered();

        return view('pages.master.clauses', [
            'title' => __('master.clauses'),
            'groups' => $criteria->groupBy(fn (Smk3Criterion $criterion) => $criterion->element())->sortKeys(),
            'elements' => config('smk3.elements'),
            'summary' => Smk3Criterion::summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCriterion($request);

        Smk3Criterion::create($validated + ['nomor' => (int) Smk3Criterion::max('nomor') + 1]);

        return redirect()->route('master.clauses')->with('success', __('clause.created', ['number' => $validated['no_kriteria']]));
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

        return redirect()->route('master.clauses')->with('success', __('clause.updated', ['number' => $criterion->no_kriteria]));
    }

    public function destroy(Smk3Criterion $criterion): RedirectResponse
    {
        if ($used = $criterion->findingsCount()) {
            return redirect()->route('master.clauses')->with('error', __('clause.inUse', ['number' => $criterion->no_kriteria, 'count' => $used]));
        }

        $criterion->delete();

        return redirect()->route('master.clauses')->with('success', __('clause.deleted', ['number' => $criterion->no_kriteria]));
    }

    /**
     * Inline checkbox in the table: switch one category on or off.
     */
    public function toggleCategory(Request $request, Smk3Criterion $criterion): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(Smk3Criterion::CATEGORIES))],
            'checked' => ['required', 'boolean'],
        ]);

        $criterion->update([$validated['category'] => $validated['checked']]);

        return response()->json([
            'criterion' => $criterion->only(['id', ...array_keys(Smk3Criterion::CATEGORIES)]),
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
