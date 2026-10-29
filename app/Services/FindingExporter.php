<?php

namespace App\Services;

use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SMK3 inspection report export, laid out like the legacy report templates
 * (template_pdf.pdf / template_excel.xlsx):
 *
 *  - PDF: title, print time and logo, the "Progress & Kriteria" summary per department,
 *    the status summary, then the Non Conformities List with the photos embedded;
 *  - Excel: sheet "Progress & Kriteria SMK3" (summary) and sheet "Non Conformities List"
 *    (photos embedded in their cells).
 *
 * The user picks which columns of the Non Conformities List are included.
 */
class FindingExporter
{
    /** Column key => [label translation key, Excel width, ticked by default]. Order = template order. */
    private const COLUMNS = [
        'id' => ['nc.export.no', 10, true],
        'tanggal' => ['nc.columns.date', 13, true],
        'pabrik' => ['nc.columns.plant', 9, false],
        'departemen' => ['nc.columns.department', 18, true],
        'deskripsi_temuan' => ['nc.columns.finding', 45, true],
        'foto_temuan' => ['nc.export.findingPicture', 30, true],
        'no_klausul' => ['nc.export.clauseNo', 11, true],
        'kategori' => ['nc.columns.category', 11, true],
        'elemen' => ['nc.export.element', 32, true],
        'kriteria' => ['nc.export.criterion', 45, false],
        'pic' => ['nc.columns.pic', 34, true],
        'pic_name' => ['nc.export.picName', 26, false],
        'foto_perbaikan' => ['nc.export.repairPicture', 30, true],
        'status' => ['nc.columns.status', 11, true],
    ];

    private const PHOTO_COLUMNS = ['foto_temuan', 'foto_perbaikan'];

    /** Summary sheet / table columns: key => translation key. */
    private const SUMMARY_COLUMNS = [
        'departemen' => 'nc.columns.department',
        'temuan' => 'nc.report.findings',
        'closed' => 'nc.closed',
        'open' => 'nc.open',
        'closed_percent' => 'nc.report.closedPercent',
        'closed_score' => 'nc.report.score',
        'minor' => 'Minor',
        'major' => 'Major',
        'kritis' => 'Kritis',
        'total_nc' => 'nc.report.totalNc',
        'total_kriteria' => 'nc.report.totalCriteria',
        'achievement' => 'nc.report.achievement',
        'achievement_score' => 'nc.report.score',
        'total_score' => 'nc.report.totalScore',
    ];

    /** Most rows one export may hold; more than this needs narrower filters. */
    public const MAX_ROWS = 5000;

    /** Height of an embedded photo, in pixels. */
    private const PHOTO_HEIGHT = 120;

    public function __construct(private FindingReport $report, private ImageCompressor $images) {}

    /**
     * Columns for the picker.
     *
     * @return list<array{key: string, label: string, default: bool}>
     */
    public static function columns(): array
    {
        return array_map(
            fn (string $key, array $column) => ['key' => $key, 'label' => __($column[0]), 'default' => $column[2]],
            array_keys(self::COLUMNS),
            self::COLUMNS,
        );
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @param  list<string>  $columns
     */
    public function excel(Collection $findings, array $columns, string $totalLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle(__('nc.report.title'))->setCreator(config('app.name'));

        $this->summarySheet($spreadsheet->getActiveSheet(), $this->summary($findings, $totalLabel));
        $this->listSheet($spreadsheet->createSheet(), $findings, $this->ordered($columns));
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @param  list<string>  $columns
     * @param  list<string>  $filters  human readable active filters, printed under the title
     */
    public function pdf(Collection $findings, array $columns, string $totalLabel, array $filters = []): DomPdf
    {
        $columns = $this->ordered($columns);
        $lookups = $this->lookups($columns);

        return Pdf::loadView('exports.findings-pdf', [
            'printedAt' => now(config('smk3.report.timezone')),
            'logo' => $this->images->png(public_path('assets/images/logo/indofood-cbp.webp')),
            'filters' => $filters,
            'summary' => $this->summary($findings, $totalLabel),
            'summaryHeaders' => $this->summaryHeaders(),
            'headers' => array_map(fn (string $key) => __(self::COLUMNS[$key][0]), $columns),
            'columns' => $columns,
            'rows' => $findings->map(fn (NonConformity $finding) => array_map(
                fn (string $key) => in_array($key, self::PHOTO_COLUMNS, true)
                    ? $this->images->thumbnail($finding->{$key})
                    : $this->value($finding, $key, $lookups),
                $columns,
            )),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @return array{rows: list<array<string, int|float|string>>, total: array<string, int|float|string>, status: array{open: int, closed: int}}
     */
    private function summary(Collection $findings, string $totalLabel): array
    {
        return $this->report->summary($findings, Smk3Criterion::count(), $totalLabel);
    }

    /** @return array<string, string> */
    private function summaryHeaders(): array
    {
        return array_map(fn (string $key) => __($key), self::SUMMARY_COLUMNS);
    }

    /**
     * Sheet 1, like the template: one header row, a row per department and the bold total row,
     * followed by the status summary.
     *
     * @param  array{rows: list<array<string, int|float|string>>, total: array<string, int|float|string>, status: array{open: int, closed: int}}  $summary
     */
    private function summarySheet(Worksheet $sheet, array $summary): void
    {
        $sheet->setTitle(mb_substr(__('nc.report.summarySheet'), 0, 31));
        $sheet->fromArray(array_values($this->summaryHeaders()));

        $rows = [...$summary['rows'], $summary['total']];
        foreach ($rows as $index => $row) {
            $sheet->fromArray(array_map(fn (string $key) => $row[$key], array_keys(self::SUMMARY_COLUMNS)), null, 'A'.($index + 2), true);
        }

        $last = Coordinate::stringFromColumnIndex(count(self::SUMMARY_COLUMNS));
        $lastRow = count($rows) + 1;
        $this->tableStyle($sheet, "A1:{$last}{$lastRow}", "A1:{$last}1");
        $sheet->getStyle("A{$lastRow}:{$last}{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.00');

        foreach (['A' => 30, 'B' => 10, 'C' => 10, 'D' => 8, 'E' => 11, 'F' => 9, 'G' => 9, 'H' => 9, 'I' => 10, 'J' => 12, 'K' => 15, 'L' => 9, 'M' => 9, 'N' => 13] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // status summary under the table
        $start = $lastRow + 2;
        $sheet->setCellValue("A{$start}", __('nc.report.summaryStatus'));
        $sheet->fromArray([[__('nc.open'), $summary['status']['open']], [__('nc.closed'), $summary['status']['closed']]], null, 'A'.($start + 1), true);
        $sheet->mergeCells("A{$start}:B{$start}");
        $this->tableStyle($sheet, "A{$start}:B".($start + 2), "A{$start}:B{$start}");
        $sheet->getStyle('A'.($start + 1).':A'.($start + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    /**
     * Sheet 2, like the template: the findings with their photos embedded in the cells.
     *
     * @param  Collection<int, NonConformity>  $findings
     * @param  list<string>  $columns
     */
    private function listSheet(Worksheet $sheet, Collection $findings, array $columns): void
    {
        $sheet->setTitle(mb_substr(__('nc.report.listTitle'), 0, 31));
        $sheet->fromArray(array_map(fn (string $key) => __(self::COLUMNS[$key][0]), $columns));

        $lookups = $this->lookups($columns);
        $hasPhotos = array_intersect(self::PHOTO_COLUMNS, $columns) !== [];

        foreach ($findings->values() as $index => $finding) {
            $row = $index + 2;
            $rowHasPhoto = false;

            foreach ($columns as $position => $key) {
                $cell = Coordinate::stringFromColumnIndex($position + 1).$row;

                if (in_array($key, self::PHOTO_COLUMNS, true)) {
                    $thumbnail = $this->images->thumbnail($finding->{$key});
                    if ($thumbnail) {
                        $drawing = new Drawing;
                        $drawing->setPath($thumbnail);
                        $drawing->setHeight(self::PHOTO_HEIGHT);
                        $drawing->setCoordinates($cell);
                        $drawing->setOffsetX(4);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $rowHasPhoto = true;
                    } else {
                        $sheet->setCellValue($cell, '-');
                    }

                    continue;
                }

                $sheet->setCellValueExplicit($cell, $this->value($finding, $key, $lookups), DataType::TYPE_STRING);
            }

            if ($hasPhotos && $rowHasPhoto) {
                // points; leaves a small margin around the photo
                $sheet->getRowDimension($row)->setRowHeight((self::PHOTO_HEIGHT + 10) * 0.75);
            }
        }

        $last = Coordinate::stringFromColumnIndex(count($columns));
        $lastRow = max(1, $findings->count() + 1);
        $this->tableStyle($sheet, "A1:{$last}{$lastRow}", "A1:{$last}1");
        foreach ($columns as $position => $key) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($position + 1))->setWidth(self::COLUMNS[$key][1]);
        }
        $sheet->freezePane('A2');
    }

    /** Template look: grey bold header, thin borders, centred and wrapped text. */
    private function tableStyle(Worksheet $sheet, string $range, string $header): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle($header)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ]);
    }

    /**
     * Ticked columns in the fixed column order (the picker may send them in any order).
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function ordered(array $columns): array
    {
        return array_values(array_intersect(array_keys(self::COLUMNS), $columns));
    }

    /**
     * Criterion texts, only loaded when that column is exported.
     *
     * @param  list<string>  $columns
     * @return array{criteria: array<string, string>}
     */
    private function lookups(array $columns): array
    {
        return [
            'criteria' => in_array('kriteria', $columns, true) ? Smk3Criterion::pluck('kriteria', 'no_kriteria')->all() : [],
        ];
    }

    /** @param  array{criteria: array<string, string>}  $lookups */
    private function value(NonConformity $finding, string $key, array $lookups): string
    {
        $element = (int) strtok((string) $finding->no_klausul, '.');

        return match ($key) {
            'id' => (string) $finding->id,
            'tanggal' => $finding->tanggal->format('d-m-Y'),
            'pabrik' => strtoupper((string) $finding->pabrik),
            'departemen' => (string) $finding->departmentName(),
            'elemen' => (string) (config('smk3.elements')[$element] ?? '-'),
            'kriteria' => $lookups['criteria'][$finding->no_klausul] ?? '-',
            'pic' => (string) $finding->pic,
            'pic_name' => $finding->picUser?->name ?? '-',
            'status' => $finding->isClosed() ? __('nc.closed') : __('nc.open'),
            default => (string) ($finding->{$key} ?? '-'),
        };
    }
}
