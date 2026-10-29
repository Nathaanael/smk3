<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

function criterion(array $attributes = []): Smk3Criterion
{
    return Smk3Criterion::create($attributes + [
        'nomor' => (int) Smk3Criterion::max('nomor') + 1,
        'no_kriteria' => '1.1.1',
        'kriteria' => 'Terdapat kebijakan K3 sesuai ketentuan',
        'minor' => true,
        'major' => true,
        'kritis' => false,
    ]);
}

/** Build an .xlsx upload from rows (first row = header). */
function clauseWorkbook(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'clauses').'.xlsx';
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    return new UploadedFile($path, 'klausul.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function auditor(): User
{
    return User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);
}

test('the page lists criteria grouped per element, in clause number order', function () {
    criterion(['no_kriteria' => '1.10.1', 'kriteria' => 'Kriteria sepuluh']);
    criterion(['no_kriteria' => '1.2.1', 'kriteria' => 'Kriteria dua']);
    criterion(['no_kriteria' => '6.7.2', 'kriteria' => 'Keadaan darurat']);

    $this->actingAs(auditor())
        ->get('/master/clauses')
        ->assertOk()
        ->assertSeeInOrder(['Kriteria dua', 'Kriteria sepuluh', 'Keadaan darurat'])
        ->assertSee('Keamanan Bekerja Berdasarkan SMK3');
});

test('auditees cannot open the clause master', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee]))
        ->get('/master/clauses')
        ->assertForbidden();
});

test('a category checkbox is saved over ajax', function () {
    $criterion = criterion();

    $this->actingAs(auditor())
        ->patchJson("/master/clauses/{$criterion->id}/category", ['category' => 'kritis', 'checked' => true])
        ->assertOk()
        ->assertJsonPath('criterion.kritis', true)
        ->assertJsonPath('summary.kritis', 1);

    expect($criterion->fresh()->kritis)->toBeTrue();
});

test('only known categories can be toggled', function () {
    $criterion = criterion();

    $this->actingAs(auditor())
        ->patchJson("/master/clauses/{$criterion->id}/category", ['category' => 'kriteria', 'checked' => true])
        ->assertUnprocessable();
});

test('a clause can be added manually', function () {
    $this->actingAs(auditor())
        ->post('/master/clauses', ['no_kriteria' => '12.6.1', 'kriteria' => 'Kriteria baru', 'minor' => '1', 'major' => '0', 'kritis' => '1'])
        ->assertRedirect('/master/clauses')
        ->assertSessionHas('success');

    $criterion = Smk3Criterion::where('no_kriteria', '12.6.1')->sole();
    expect($criterion->allowedCategories())->toBe(['Minor', 'Kritis']);
});

test('clause numbers must be valid and unique', function (string $number) {
    criterion(['no_kriteria' => '1.1.1']);

    $this->actingAs(auditor())
        ->post('/master/clauses', ['no_kriteria' => $number, 'kriteria' => 'X'])
        ->assertSessionHasErrorsIn('clause', 'no_kriteria');
})->with(['1.1.1', 'abc', '7']);

test('renumbering a clause carries its findings along', function () {
    $criterion = criterion(['no_kriteria' => '6.7.2']);
    $finding = NonConformity::factory()->create(['no_klausul' => '6.7.2']);

    $this->actingAs(auditor())
        ->put("/master/clauses/{$criterion->id}", ['no_kriteria' => '6.7.3', 'kriteria' => 'Diubah', 'minor' => '1'])
        ->assertRedirect('/master/clauses');

    expect($criterion->fresh()->no_kriteria)->toBe('6.7.3')
        ->and($finding->fresh()->no_klausul)->toBe('6.7.3');
});

test('a clause used by findings cannot be deleted', function () {
    $criterion = criterion();
    NonConformity::factory()->create(['no_klausul' => $criterion->no_kriteria]);

    $this->actingAs(auditor())->delete("/master/clauses/{$criterion->id}")->assertSessionHas('error');
    expect($criterion->fresh())->not->toBeNull();
});

test('an unused clause can be deleted', function () {
    $criterion = criterion();

    $this->actingAs(auditor())->delete("/master/clauses/{$criterion->id}")->assertSessionHas('success');
    expect(Smk3Criterion::count())->toBe(0);
});

test('the template contains the current clauses and can be uploaded back', function () {
    criterion(['no_kriteria' => '1.1.1', 'minor' => true, 'major' => true, 'kritis' => false]);
    criterion(['no_kriteria' => '1.1.2', 'kriteria' => 'Kedua', 'minor' => true, 'major' => false, 'kritis' => false]);

    $response = $this->actingAs(auditor())->get('/master/clauses/template');
    $response->assertOk()->assertDownload('template-klausul-smk3.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);

    expect($rows[0])->toBe(['No Klausul', 'Kriteria', 'Minor', 'Major', 'Kritis'])
        ->and($rows[1])->toEqual(['1.1.1', 'Terdapat kebijakan K3 sesuai ketentuan', 1, 1, 0]);

    // switch Kritis on for 1.1.2 and add a new clause, then upload
    $rows[2][4] = 1;
    $rows[] = ['13.1.1', 'Klausul tambahan', 1, 0, 0];

    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => clauseWorkbook($rows)])
        ->assertRedirect('/master/clauses')
        ->assertSessionHas('success', __('clause.import.done', ['created' => 1, 'updated' => 1, 'unchanged' => 1]));

    expect(Smk3Criterion::where('no_kriteria', '1.1.2')->value('kritis'))->toBeTrue()
        ->and(Smk3Criterion::where('no_kriteria', '13.1.1')->exists())->toBeTrue();
});

test('an upload with problems saves nothing and lists every problem', function () {
    criterion(['no_kriteria' => '1.1.1', 'kritis' => false]);

    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => clauseWorkbook([
            ['No Klausul', 'Kriteria', 'Minor', 'Major', 'Kritis'],
            ['1.1.1', 'Terdapat kebijakan K3 sesuai ketentuan', 1, 1, 1],
            ['abc', 'Nomor salah', 1, 0, 0],
            ['2.1.1', 'Flag salah', 'mungkin', 0, 0],
        ])])
        ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 2);

    expect(Smk3Criterion::where('no_kriteria', '1.1.1')->value('kritis'))->toBeFalse()
        ->and(Smk3Criterion::count())->toBe(1);
});

test('an upload without the expected columns is rejected', function () {
    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => clauseWorkbook([['Nomor', 'Deskripsi'], ['1.1.1', 'X']])])
        ->assertSessionHas('importErrors', [__('clause.import.missingColumn', ['column' => 'No Klausul'])]);
});

test('only xlsx files are accepted', function () {
    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => UploadedFile::fake()->create('klausul.csv', 1, 'text/csv')])
        ->assertSessionHasErrorsIn('import', 'file');
});
