<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Http\Controllers\Controller;
use App\Http\Requests\FindingFilterRequest;
use App\Models\NonConformity;
use App\Services\FindingExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download of the findings list as Excel or PDF: the current search / filters, optionally only
 * the ticked rows, with the columns ticked in the export menu.
 */
class FindingExportController extends Controller
{
    public function __invoke(FindingFilterRequest $request, FindingExporter $exporter): Response|RedirectResponse
    {
        $options = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => [Rule::in(FindingExporter::keys())],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $request->findings()->when($options['ids'] ?? null, fn ($query, $ids) => $query->whereKey($ids));

        if ($query->count() > FindingExporter::MAX_ROWS) {
            // the list downloads in the background and shows this message itself
            if ($request->expectsJson()) {
                return response()->json(['message' => __('nc.export.tooMany', ['max' => FindingExporter::MAX_ROWS])], 422);
            }

            return redirect()->route('non-conformities', $request->only(FindingFilterRequest::FILTERS))
                ->with('error', __('nc.export.tooMany', ['max' => FindingExporter::MAX_ROWS]));
        }

        // embedding many photos takes a while
        set_time_limit(300);

        $findings = $query->get();
        $filename = 'laporan-smk3-'.now(config('smk3.report.timezone'))->format('Ymd-Hi');
        $totalLabel = $this->totalLabel($request, $findings->pluck('pabrik')->unique()->values()->all());

        if ($options['format'] === 'pdf') {
            return $exporter->pdf($findings, $options['columns'], $totalLabel, $this->describeFilters($request, (bool) ($options['ids'] ?? null)))
                ->download("{$filename}.pdf");
        }

        $workbook = $exporter->excel($findings, $options['columns'], $totalLabel);

        return response()->streamDownload(function () use ($workbook) {
            IOFactory::createWriter($workbook, 'Xlsx')->save('php://output');
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Label of the summary's total row: the plant when the report covers one plant (as in the
     * template), otherwise "Total".
     *
     * @param  list<string>  $plants  plants present in the exported findings
     */
    private function totalLabel(FindingFilterRequest $request, array $plants): string
    {
        $plant = $request->validated('pabrik') ?? (count($plants) === 1 ? $plants[0] : null);

        return $plant ? (config('smk3.plants')[$plant] ?? strtoupper($plant)) : __('nc.report.total');
    }

    /**
     * Active filters in words, printed under the PDF title.
     *
     * @return list<string>
     */
    private function describeFilters(FindingFilterRequest $request, bool $selectedOnly): array
    {
        $filters = $request->validated();
        $date = fn (string $value) => Carbon::parse($value)->translatedFormat('d M Y');
        $plant = $filters['pabrik'] ?? null;

        return array_values(array_filter([
            $selectedOnly ? __('nc.export.selectedOnly') : null,
            isset($filters['q']) ? __('nc.export.filter.search', ['value' => $filters['q']]) : null,
            $plant ? __('nc.plant').': '.(config('smk3.plants')[$plant] ?? strtoupper($plant)) : null,
            isset($filters['departemen']) ? __('nc.department').': '.(NonConformity::departmentsFor($plant)[$filters['departemen']] ?? $filters['departemen']) : null,
            isset($filters['kategori']) ? __('nc.category').': '.$filters['kategori'] : null,
            isset($filters['status']) ? __('nc.columns.status').': '.__('nc.'.$filters['status']) : null,
            isset($filters['dari']) || isset($filters['sampai'])
                ? __('nc.columns.date').': '.(isset($filters['dari']) ? $date($filters['dari']) : '…').' – '.(isset($filters['sampai']) ? $date($filters['sampai']) : '…')
                : null,
        ]));
    }
}
