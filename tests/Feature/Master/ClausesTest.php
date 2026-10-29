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

test('the page shows only the first element tab, in clause number order', function () {
    criterion(['no_kriteria' => '1.10.1', 'kriteria' => 'Kriteria sepuluh']);
    criterion(['no_kriteria' => '1.2.1', 'kriteria' => 'Kriteria dua']);
    criterion(['no_kriteria' => '6.7.2', 'kriteria' => 'Keadaan darurat']);

    $this->actingAs(auditor())
        ->get('/master/clauses')
        ->assertOk()
        ->assertSeeInOrder(['Kriteria dua', 'Kriteria sepuluh'])
        ->assertDontSee('Keadaan darurat');
});

test('another element tab is loaded as a fragment', function () {
    criterion(['no_kriteria' => '1.1.1', 'kriteria' => 'Kriteria satu']);
    criterion(['no_kriteria' => '6.7.2', 'kriteria' => 'Keadaan darurat']);

    $response = $this->actingAs(auditor())
        ->get('/master/clauses?element=6', ['X-Partial' => 'results'])
        ->assertOk();

    expect($response->getContent())
        ->toContain('Keadaan darurat')
        ->toContain('Keamanan Bekerja Berdasarkan SMK3')
        ->not->toContain('Kriteria satu')
        ->not->toContain('<html');
});

test('searching jumps to the first element with a match', function () {
    criterion(['no_kriteria' => '1.1.1', 'kriteria' => 'Kebijakan K3']);
    criterion(['no_kriteria' => '6.7.2', 'kriteria' => 'Keadaan darurat']);

    $this->actingAs(auditor())
        ->get('/master/clauses?element=1&q=darurat')
        ->assertOk()
        ->assertSee('Keadaan darurat')
        ->assertDontSee('Kebijakan K3');
});

test('auditees cannot open the clause master', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee]))
        ->get('/master/clauses')
        ->assertForbidden();
});

test('changed category checkboxes are saved together', function () {
    $first = criterion(['no_kriteria' => '1.1.1', 'minor' => true, 'major' => true, 'kritis' => false]);
    $second = criterion(['no_kriteria' => '1.1.2', 'minor' => true, 'major' => true, 'kritis' => false]);

    $this->actingAs(auditor())
        ->patchJson('/master/clauses/categories', ['changes' => [
            ['id' => $first->id, 'minor' => true, 'major' => true, 'kritis' => true],
            ['id' => $second->id, 'minor' => false, 'major' => true, 'kritis' => false],
        ]])
        ->assertOk()
        ->assertJsonPath('message', __('clause.categoriesSaved', ['count' => 2]))
        ->assertJsonPath('summary.kritis', 1)
        ->assertJsonPath('summary.minor', 1);

    expect($first->fresh()->kritis)->toBeTrue()
        ->and($second->fresh()->minor)->toBeFalse();
});

test('nothing is saved when one change is invalid', function () {
    $criterion = criterion(['kritis' => false]);

    $this->actingAs(auditor())
        ->patchJson('/master/clauses/categories', ['changes' => [
            ['id' => $criterion->id, 'minor' => true, 'major' => true, 'kritis' => true],
            ['id' => 999, 'minor' => true, 'major' => true, 'kritis' => true],
        ]])
        ->assertUnprocessable();

    expect($criterion->fresh()->kritis)->toBeFalse();
});

test('the summary counts every criterion per category', function () {
    criterion(['no_kriteria' => '1.1.1', 'minor' => true, 'major' => true, 'kritis' => false]);
    criterion(['no_kriteria' => '1.1.2', 'minor' => true, 'major' => true, 'kritis' => true]);
    criterion(['no_kriteria' => '1.1.3', 'minor' => true, 'major' => false, 'kritis' => false]);

    expect(Smk3Criterion::summary())->toBe(['total' => 3, 'minor' => 3, 'major' => 2, 'kritis' => 1]);
});

test('every category value must be given as true or false', function () {
    $criterion = criterion();

    $this->actingAs(auditor())
        ->patchJson('/master/clauses/categories', ['changes' => [['id' => $criterion->id, 'minor' => 'mungkin', 'major' => true]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['changes.0.minor', 'changes.0.kritis']);
});

test('a clause can be added manually', function () {
    $this->actingAs(auditor())
        ->post('/master/clauses', ['no_kriteria' => '12.6.1', 'kriteria' => 'Kriteria baru', 'minor' => '1', 'major' => '0', 'kritis' => '1'])
        ->assertRedirect('/master/clauses?element=12')
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
        ->assertRedirect('/master/clauses?element=6');

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

    expect($rows[0])->toBe(['No Klausul', 'Kriteria', 'Minor', 'Major', 'Kritis', 'Kata Kunci'])
        ->and($rows[1])->toEqual(['1.1.1', 'Terdapat kebijakan K3 sesuai ketentuan', 1, 1, 0, null]);

    // switch Kritis on for 1.1.2 and add a new clause, then upload
    $rows[2][4] = 1;
    $rows[] = ['13.1.1', 'Klausul tambahan', 1, 0, 0, 'Tikus; HAMA , tikus'];

    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => clauseWorkbook($rows)])
        ->assertRedirect('/master/clauses')
        ->assertSessionHas('success', __('clause.import.done', ['created' => 1, 'updated' => 1, 'unchanged' => 1]));

    expect(Smk3Criterion::where('no_kriteria', '1.1.2')->value('kritis'))->toBeTrue()
        ->and(Smk3Criterion::where('no_kriteria', '13.1.1')->value('kata_kunci'))->toBe('tikus, hama');
});

test('keywords are saved normalised and shown in the table', function () {
    $this->actingAs(auditor())
        ->post('/master/clauses', ['no_kriteria' => '6.4.9', 'kriteria' => 'Kebersihan area kerja', 'kata_kunci' => ' Tikus,hama ;  KECOA,tikus', 'minor' => '1'])
        ->assertSessionHas('success');

    expect(Smk3Criterion::where('no_kriteria', '6.4.9')->value('kata_kunci'))->toBe('tikus, hama, kecoa');

    $this->actingAs(auditor())->get('/master/clauses?element=6')->assertSeeInOrder(['Kebersihan area kerja', 'tikus', 'hama', 'kecoa']);
});

test('an upload without the Kata Kunci column keeps the keywords', function () {
    criterion(['no_kriteria' => '1.1.1', 'kata_kunci' => 'tikus']);

    $this->actingAs(auditor())
        ->post('/master/clauses/import', ['file' => clauseWorkbook([
            ['No Klausul', 'Kriteria', 'Minor', 'Major', 'Kritis'],
            ['1.1.1', 'Teks baru', 1, 1, 0],
        ])])
        ->assertSessionHas('success');

    expect(Smk3Criterion::where('no_kriteria', '1.1.1')->value('kata_kunci'))->toBe('tikus');
});

test('the template can hold only the chosen elements', function () {
    criterion(['no_kriteria' => '1.1.1']);
    criterion(['no_kriteria' => '6.7.2', 'kriteria' => 'Keadaan darurat']);
    criterion(['no_kriteria' => '12.1.1', 'kriteria' => 'Pelatihan']);

    $response = $this->actingAs(auditor())->get('/master/clauses/template?'.http_build_query(['elements' => [12, 6]]));
    $response->assertOk()->assertDownload('template-klausul-smk3-elemen-6-12.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $numbers = array_column(array_slice(IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false), 1), 0);

    expect(array_values(array_filter($numbers)))->toBe(['6.7.2', '12.1.1']);
});

test('unknown elements are refused with a message for the loading toast', function () {
    criterion(['no_kriteria' => '1.1.1']);

    $this->actingAs(auditor())
        ->getJson('/master/clauses/template?'.http_build_query(['elements' => [99]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('elements.0');
});

test('the template menu lists every element with its number of clauses', function () {
    criterion(['no_kriteria' => '6.7.2']);
    criterion(['no_kriteria' => '6.7.3']);

    $this->actingAs(auditor())->get('/master/clauses')
        ->assertOk()
        ->assertSee(__('clause.template.chooseElements'))
        ->assertSeeInOrder([__('clause.element', ['number' => 6]), 'Keamanan Bekerja Berdasarkan SMK3', '2']);
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
