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
 *    the status summary, optionally the Progress Monitoring SMK3 recap, then the
 *    Non Conformities List with the photos embedded;
 *  - Excel: sheet "Progress & Kriteria SMK3" (summary), optionally sheet "Progress Monitoring
 *    SMK3", then sheet "Non Conformities List" (photos embedded in their cells).
 *
 * The user picks which columns of the Non Conformities List are included, and whether the
 * Progress Monitoring recap (see ProgressMonitor; one table per plant) is.
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
     * @param  list<array{plant: string, period: string, recap: array<string, mixed>}>  $progress
     */
    public function excel(Collection $findings, array $columns, string $totalLabel, array $progress = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle(__('nc.report.title'))->setCreator(config('app.name'));

        $this->summarySheet($spreadsheet->getActiveSheet(), $this->summary($findings, $totalLabel));
        if ($progress) {
            $this->progressSheet($spreadsheet->createSheet(), $progress);
        }
        $this->listSheet($spreadsheet->createSheet(), $findings, $this->ordered($columns));
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  Collection<int, NonConformity>  $findings
     * @param  list<string>  $columns
     * @param  list<string>  $filters  human readable active filters, printed under the title
     * @param  list<array{plant: string, period: string, recap: array<string, mixed>}>  $progress
     */
    public function pdf(Collection $findings, array $columns, string $totalLabel, array $filters = [], array $progress = []): DomPdf
    {
        $columns = $this->ordered($columns);
        $lookups = $this->lookups($columns);

        return Pdf::loadView('exports.findings-pdf', [
            'printedAt' => now(config('smk3.report.timezone')),
            'logo' => $this->images->png(public_path('assets/images/logo/indofood-cbp.webp')),
            'filters' => $filters,
            'summary' => $this->summary($findings, $totalLabel),
            'summaryHeaders' => $this->summaryHeaders(),
            'progress' => $progress,
            'progressNotes' => $progress ? $this->progressNotes($progress[0]['recap']['criteria']) : [],
            'tone' => fn (int|float $value) => $this->tone($value),
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
     * Sheet "Progress Monitoring SMK3": per plant a title row (plant and period), the two-row
     * header of the page, a row per department and the total row; the scoring notes at the end.
     * Score / SMK3 rating / Total Ranking cells are coloured like the badges on the page.
     *
     * @param  list<array{plant: string, period: string, recap: array<string, mixed>}>  $progress
     */
    private function progressSheet(Worksheet $sheet, array $progress): void
    {
        $sheet->setTitle(mb_substr(__('smk3.progressMonitoring'), 0, 31));
        $row = 1;

        foreach ($progress as $block) {
            $sheet->setCellValue("A{$row}", __('smk3.progressMonitoring').' — '.$block['plant'].' · '.$block['period']);
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);

            $head = $row + 1;
            $sheet->fromArray([
                [__('pm.columns.no'), __('nc.columns.department'), __('pm.groups.progress'), null, null, null, __('pm.groups.deviation'), null, null, null, null, __('pm.columns.ranking')],
                [null, null, __('pm.columns.total'), __('nc.closed'), __('nc.open'), __('pm.columns.closedPercent'), 'Minor', 'Major', 'Kritis', __('pm.columns.score'), __('pm.columns.rating'), null],
            ], null, "A{$head}", true);
            foreach (['A', 'B', 'L'] as $column) {
                $sheet->mergeCells("{$column}{$head}:{$column}".($head + 1));
            }
            $sheet->mergeCells("C{$head}:F{$head}");
            $sheet->mergeCells("G{$head}:K{$head}");

            $current = $head + 2;
            foreach ($block['recap']['rows'] as $index => $department) {
                $sheet->fromArray([
                    $index + 1, $department['name'], $department['total'], $department['closed'], $department['open'],
                    $department['total'] ? $department['closed_percent'] : '-',
                    $department['minor'], $this->majorLabel($department), $department['kritis'], $department['score'],
                    $department['satisfactory'] ? __('pm.satisfactory') : __('pm.poor'), $department['ranking'],
                ], null, "A{$current}", true);

                $this->fill($sheet, "J{$current}", $this->tone($department['score']));
                $this->fill($sheet, "K{$current}", $department['satisfactory'] ? 'good' : 'poor');
                $this->fill($sheet, "L{$current}", $this->tone($department['ranking']));
                $current++;
            }

            $total = $block['recap']['total'];
            $sheet->fromArray([
                null, __('pm.total'), $total['total'], $total['closed'], $total['open'],
                $total['total'] ? $total['closed_percent'] : '-', $total['minor'], $this->majorLabel($total), $total['kritis'],
            ], null, "A{$current}", true);

            $this->tableStyle($sheet, "A{$head}:L{$current}", "A{$head}:L".($head + 1));
            $sheet->getStyle("A{$current}:L{$current}")->getFont()->setBold(true);
            $sheet->getStyle('B'.($head + 2).":B{$current}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            foreach (['F', 'J', 'L'] as $column) {
                $sheet->getStyle($column.($head + 2).":{$column}{$current}")->getNumberFormat()->setFormatCode('0.00"%"');
            }

            $row = $current + 3;
        }

        // how the recap is scored
        $sheet->setCellValue("A{$row}", __('pm.export.notes'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        foreach ($this->progressNotes($progress[0]['recap']['criteria']) as $note) {
            $row++;
            $sheet->setCellValue("A{$row}", $note);
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getRowDimension($row)->setRowHeight(30);
        }

        foreach (['A' => 6, 'B' => 32, 'C' => 9, 'D' => 9, 'E' => 9, 'F' => 11, 'G' => 9, 'H' => 10, 'I' => 9, 'J' => 11, 'K' => 14, 'L' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Scoring rules, one line per measure, for the notes under the recap.
     *
     * @return list<string>
     */
    private function progressNotes(int $criteria): array
    {
        $notes = [
            __('pm.columns.score') => [__('pm.guide.score.formula', ['criteria' => $criteria]), __('pm.guide.score.text', ['criteria' => $criteria])],
            __('pm.guide.penalty.title') => [__('pm.guide.penalty.formula'), __('pm.guide.penalty.text', ['min' => ProgressMonitor::MINOR_TO_MAJOR])],
            __('pm.columns.rating') => [__('pm.guide.rating.formula', ['min' => ProgressMonitor::SATISFACTORY]), __('pm.guide.rating.text')],
            __('pm.columns.ranking') => [__('pm.guide.ranking.formula'), __('pm.guide.ranking.text')],
        ];

        return array_map(fn (string $title, array $note) => "{$title}: {$note[0]}. {$note[1]}", array_keys($notes), $notes);
    }

    /**
     * Major count, plus the Majors that come from repeated Minor findings: "1 (+2)".
     *
     * @param  array<string, int|float>  $counts
     */
    private function majorLabel(array $counts): int|string
    {
        return $counts['converted_major'] ? "{$counts['major']} (+{$counts['converted_major']})" : $counts['major'];
    }

    /** Colour band of a Score / Total Ranking, like the badges on the page. */
    private function tone(int|float $value): string
    {
        return match (true) {
            $value >= ProgressMonitor::SATISFACTORY => 'good',
            $value >= 60 => 'fair',
            default => 'poor',
        };
    }

    private function fill(Worksheet $sheet, string $cell, string $tone): void
    {
        $colors = ['good' => 'DCFCE7', 'fair' => 'FEF3C7', 'poor' => 'FEE2E2'];
        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colors[$tone]);
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
