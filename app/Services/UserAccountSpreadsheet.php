<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel template download and bulk upload for user accounts of one role (auditor, auditee).
 *
 * Layout (first sheet, header on row 1): Nama | Email | Password | Departemen | Pabrik.
 * A second "Referensi" sheet lists the valid plant and department codes.
 */
class UserAccountSpreadsheet
{
    public const HEADERS = ['Nama', 'Email', 'Password', 'Departemen', 'Pabrik'];

    public const MIN_PASSWORD = 8;

    /** Rows below the data that still get the dropdowns, for adding new accounts. */
    private const SPARE_ROWS = 100;

    /**
     * Template filled with the current accounts (without passwords), so it can be
     * edited and uploaded back.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, string>  $plantOptions  users.pabrik values the uploader may use
     */
    public function template(Collection $users, array $plantOptions, string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($title)->setCreator(config('app.name'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray(self::HEADERS);
        $sheet->fromArray($users->map(fn (User $user) => [
            $user->name,
            $user->email,
            null,
            $user->departemen,
            $user->pabrik,
        ])->all(), null, 'A2', true);

        $lastRow = $users->count() + 1;
        $validationEnd = $lastRow + self::SPARE_ROWS;

        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F4F7']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D5DD']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle("A1:E{$validationEnd}")->getNumberFormat()->setFormatCode('@');
        foreach (['A' => 30, 'B' => 36, 'C' => 18, 'D' => 16, 'E' => 12] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getComment('C1')->getText()->createTextRun(__('account.template.passwordNote', ['min' => self::MIN_PASSWORD]));

        $departments = array_merge(...array_map(fn (string $plant) => User::departmentsForPlant($plant), array_keys($plantOptions)));
        $reference = $this->referenceSheet($spreadsheet, $plantOptions, $departments);

        $sheet->setDataValidation("D2:D{$validationEnd}", $this->listValidation("'{$reference->getTitle()}'!\$D\$2:\$D\$".(count($departments) + 1)));
        $sheet->setDataValidation("E2:E{$validationEnd}", $this->listValidation("'{$reference->getTitle()}'!\$A\$2:\$A\$".(count($plantOptions) + 1)));

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:E{$lastRow}");
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Read an uploaded template. Every problem is collected so they can all be fixed at once.
     *
     * @param  array<string, string>  $plantOptions  users.pabrik values the uploader may use
     * @return array{rows: list<array{row: int, user: ?User, name: string, email: string, password: ?string, departemen: string, pabrik: string}>, errors: list<string>}
     */
    public function read(UploadedFile $file, Role $role, array $plantOptions): array
    {
        $data = IOFactory::load($file->getRealPath())->getSheet(0)->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), $data[0] ?? []);
        $columns = [];
        foreach (self::HEADERS as $name) {
            $index = array_search(mb_strtolower($name), $header, true);
            if ($index === false) {
                return ['rows' => [], 'errors' => [__('account.import.missingColumn', ['column' => $name])]];
            }
            $columns[$name] = $index;
        }

        $emails = collect(array_slice($data, 1))->map(fn ($cells) => mb_strtolower(trim((string) ($cells[$columns['Email']] ?? ''))))->filter();
        $existing = User::whereIn('email', $emails->all())->get()->keyBy(fn (User $user) => mb_strtolower($user->email));
        $allowedPlants = array_values(array_intersect(array_keys(config('smk3.plants')), array_keys($plantOptions)));

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($data, 1, null, true) as $index => $cells) {
            $line = $index + 1;
            $value = fn (string $name) => trim((string) ($cells[$columns[$name]] ?? ''));
            $row = array_combine(['name', 'email', 'password', 'departemen', 'pabrik'], array_map($value, self::HEADERS));

            if (implode('', $row) === '') {
                continue;
            }

            $problem = $this->problem($row, $line, $role, $plantOptions, $allowedPlants, $existing, $seen);
            if ($problem !== null) {
                $errors[] = $problem;

                continue;
            }

            $pabrik = $this->plant($row['pabrik']);
            $rows[] = [
                'row' => $line,
                'user' => $existing->get(mb_strtolower($row['email'])),
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => $row['password'] === '' ? null : $row['password'],
                'departemen' => $this->department($row['departemen'], $pabrik),
                'pabrik' => $pabrik,
            ];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = __('account.import.empty');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Create new accounts and update existing ones (matched on email).
     * Accounts missing from the file are left untouched; a blank password keeps the old one.
     *
     * @param  list<array{user: ?User, name: string, email: string, password: ?string, departemen: string, pabrik: string}>  $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function import(array $rows, Role $role): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        DB::transaction(function () use ($rows, $role, &$result) {
            foreach ($rows as $row) {
                $attributes = ['name' => $row['name'], 'departemen' => $row['departemen'], 'pabrik' => $row['pabrik']];

                if (! $row['user']) {
                    User::create($attributes + ['email' => $row['email'], 'password' => $row['password'], 'otorisasi' => $role]);
                    $result['created']++;

                    continue;
                }

                $user = $row['user']->fill($attributes);
                if ($row['password'] !== null) {
                    $user->password = $row['password'];
                }

                if ($user->isDirty()) {
                    $user->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
            }
        });

        return $result;
    }

    /**
     * @param  array{name: string, email: string, password: string, departemen: string, pabrik: string}  $row
     * @param  array<string, string>  $plantOptions
     * @param  list<string>  $allowedPlants
     * @param  Collection<string, User>  $existing
     * @param  array<string, int>  $seen
     */
    private function problem(array $row, int $line, Role $role, array $plantOptions, array $allowedPlants, Collection $existing, array &$seen): ?string
    {
        if ($row['name'] === '') {
            return __('account.import.missingName', ['row' => $line]);
        }
        if (mb_strlen($row['name']) > 255) {
            return __('account.import.nameTooLong', ['row' => $line]);
        }
        if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return __('account.import.badEmail', ['row' => $line, 'value' => $row['email']]);
        }

        $key = mb_strtolower($row['email']);
        if (isset($seen[$key])) {
            return __('account.import.duplicate', ['row' => $line, 'email' => $row['email'], 'first' => $seen[$key]]);
        }
        $seen[$key] = $line;

        $user = $existing->get($key);
        if ($user && $user->otorisasi !== $role) {
            return __('account.import.otherRole', ['row' => $line, 'email' => $row['email'], 'role' => $user->otorisasi->name]);
        }
        if ($user && array_diff($user->plantCodes(), $allowedPlants) !== []) {
            return __('account.import.notAllowed', ['row' => $line, 'email' => $row['email']]);
        }

        $pabrik = $this->plant($row['pabrik']);
        if (! isset($plantOptions[$pabrik])) {
            return __('account.import.badPlant', ['row' => $line, 'value' => $row['pabrik']]);
        }
        if ($this->department($row['departemen'], $pabrik) === null) {
            return __('account.import.badDepartment', ['row' => $line, 'value' => $row['departemen'], 'plant' => strtoupper($pabrik)]);
        }

        if ($row['password'] === '' && ! $user) {
            return __('account.import.missingPassword', ['row' => $line]);
        }
        if ($row['password'] !== '' && mb_strlen($row['password']) < self::MIN_PASSWORD) {
            return __('account.import.shortPassword', ['row' => $line, 'min' => self::MIN_PASSWORD]);
        }

        return null;
    }

    /** "CKP + ING" / "ckp, ing" -> "ckp,ing". */
    private function plant(string $value): string
    {
        return implode(',', array_filter(preg_split('/\s*[,+]\s*/', mb_strtolower($value))));
    }

    /** Department code from a code or name, case-insensitive; null when unknown for the plant. */
    private function department(string $value, string $pabrik): ?string
    {
        $value = mb_strtolower($value);

        foreach (User::departmentsForPlant($pabrik) as $code => $name) {
            if ($value === mb_strtolower($code) || $value === mb_strtolower($name)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $plantOptions
     * @param  array<string, string>  $departments
     */
    private function referenceSheet(Spreadsheet $spreadsheet, array $plantOptions, array $departments): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Referensi');

        $sheet->fromArray(['Pabrik', 'Keterangan'], null, 'A1');
        $sheet->fromArray(array_map(null, array_keys($plantOptions), array_values($plantOptions)), null, 'A2', true);
        $sheet->fromArray(['Departemen', 'Keterangan'], null, 'D1');
        $sheet->fromArray(array_map(null, array_keys($departments), array_values($departments)), null, 'D2', true);

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A:E')->getNumberFormat()->setFormatCode('@');
        foreach (['A' => 12, 'B' => 30, 'D' => 16, 'E' => 34] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return $sheet;
    }

    private function listValidation(string $range): DataValidation
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle(__('account.template.invalidTitle'))
            ->setError(__('account.template.invalidText'))
            ->setFormula1($range);

        return $validation;
    }
}
