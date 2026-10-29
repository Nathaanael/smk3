<?php

namespace App\Services;

use App\Models\Smk3Criterion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel template download and bulk upload for the SMK3 criteria master.
 *
 * Layout (one sheet, header on row 1): No Klausul | Kriteria | Minor | Major | Kritis,
 * where the category columns hold 1 (allowed) or 0 (not allowed).
 */
class Smk3CriterionSpreadsheet
{
    public const HEADERS = ['No Klausul', 'Kriteria', 'Minor', 'Major', 'Kritis'];

    /** Rows below the data that still get the 1/0 dropdown, for adding new criteria. */
    private const SPARE_ROWS = 100;

    private const TRUE_VALUES = ['1', 'ya', 'y', 'yes', 'true', 'x', 'v', '✓', '✔'];

    private const FALSE_VALUES = ['', '0', 'tidak', 't', 'no', 'n', 'false', '-'];

    /**
     * Template filled with the current criteria, so it can be edited and uploaded back.
     *
     * @param  Collection<int, Smk3Criterion>  $criteria
     */
    public function template(Collection $criteria): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle('Klausul SMK3')->setCreator(config('app.name'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Klausul SMK3');
        $sheet->fromArray(self::HEADERS);

        $sheet->fromArray($criteria->map(fn (Smk3Criterion $criterion) => [
            $criterion->no_kriteria,
            $criterion->kriteria,
            (int) $criterion->minor,
            (int) $criterion->major,
            (int) $criterion->kritis,
        ])->all(), null, 'A2', true);

        $lastRow = $criteria->count() + 1;
        $validationEnd = $lastRow + self::SPARE_ROWS;

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F4F7']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D5DD']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle("A1:A{$validationEnd}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("B2:B{$validationEnd}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A2:A{$validationEnd}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("C1:E{$validationEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(90);
        foreach (['C', 'D', 'E'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(10);
        }

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Nilai tidak valid')
            ->setError('Isi 1 jika kategori berlaku, atau 0 jika tidak.')
            ->setFormula1('"1,0"');
        $sheet->setDataValidation("C2:E{$validationEnd}", $validation);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:E{$lastRow}");

        return $spreadsheet;
    }

    /**
     * Read an uploaded template.
     *
     * @return array{rows: list<array{row: int, no_kriteria: string, kriteria: string, minor: bool, major: bool, kritis: bool}>, errors: list<string>}
     */
    public function read(UploadedFile $file): array
    {
        $sheet = IOFactory::load($file->getRealPath())->getSheet(0);
        $data = $sheet->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), $data[0] ?? []);
        $columns = [];
        foreach (self::HEADERS as $name) {
            $index = array_search(mb_strtolower($name), $header, true);
            if ($index === false) {
                return ['rows' => [], 'errors' => [__('clause.import.missingColumn', ['column' => $name])]];
            }
            $columns[$name] = $index;
        }

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($data, 1, null, true) as $index => $cells) {
            $line = $index + 1;
            $value = fn (string $name) => trim((string) ($cells[$columns[$name]] ?? ''));

            $number = $value('No Klausul');
            $text = $value('Kriteria');

            if ($number === '' && $text === '') {
                continue;
            }

            if (! preg_match(Smk3Criterion::NUMBER_PATTERN, $number)) {
                $errors[] = __('clause.import.badNumber', ['row' => $line, 'value' => $number]);

                continue;
            }
            if (isset($seen[$number])) {
                $errors[] = __('clause.import.duplicate', ['row' => $line, 'number' => $number, 'first' => $seen[$number]]);

                continue;
            }
            $seen[$number] = $line;

            if ($text === '') {
                $errors[] = __('clause.import.missingText', ['row' => $line, 'number' => $number]);

                continue;
            }

            $flags = [];
            foreach (Smk3Criterion::CATEGORIES as $column => $label) {
                $flag = $this->flag($value($label));
                if ($flag === null) {
                    $errors[] = __('clause.import.badFlag', ['row' => $line, 'column' => $label, 'value' => $value($label)]);

                    continue 2;
                }
                $flags[$column] = $flag;
            }

            $rows[] = ['row' => $line, 'no_kriteria' => $number, 'kriteria' => $text] + $flags;
        }

        if ($rows === [] && $errors === []) {
            $errors[] = __('clause.import.empty');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Insert new criteria and update existing ones (matched on clause number).
     * Criteria missing from the file are left untouched.
     *
     * @param  list<array{no_kriteria: string, kriteria: string, minor: bool, major: bool, kritis: bool}>  $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function import(array $rows): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        DB::transaction(function () use ($rows, &$result) {
            $existing = Smk3Criterion::all()->keyBy('no_kriteria');
            $nextNumber = (int) $existing->max('nomor') + 1;

            foreach ($rows as $row) {
                $attributes = collect($row)->only(['kriteria', 'minor', 'major', 'kritis'])->all();
                $criterion = $existing->get($row['no_kriteria']);

                if (! $criterion) {
                    Smk3Criterion::create($attributes + ['no_kriteria' => $row['no_kriteria'], 'nomor' => $nextNumber++]);
                    $result['created']++;

                    continue;
                }

                $criterion->fill($attributes);
                if ($criterion->isDirty()) {
                    $criterion->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
            }
        });

        return $result;
    }

    /** 1/0 style cell value to bool; null when the value is not understood. */
    private function flag(string $value): ?bool
    {
        $value = mb_strtolower($value);

        return match (true) {
            in_array($value, self::TRUE_VALUES, true) => true,
            in_array($value, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }
}
