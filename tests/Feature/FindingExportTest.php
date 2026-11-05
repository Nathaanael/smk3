<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);
});

function exportedWorkbook($response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return IOFactory::load($path);
}

/** Rows of the "Non Conformities List" sheet of a downloaded workbook. */
function exportedRows($response): array
{
    return exportedWorkbook($response)->getSheet(1)->toArray(null, true, true, false);
}

test('excel export holds only the ticked columns, in the template order', function () {
    NonConformity::factory()->open()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Kabel terkelupas', 'no_klausul' => '6.5.3'])
        ->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/foto_temuan/hilang.jpg']);

    $response = $this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['status', 'elemen', 'deskripsi_temuan', 'foto_temuan']]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $rows = exportedRows($response);
    expect($rows[0])->toBe([__('nc.columns.finding'), __('nc.export.findingPicture'), __('nc.export.element'), __('nc.columns.status')])
        // a photo whose file is missing shows "-" like the template
        ->and($rows[1])->toBe(['Kabel terkelupas', '-', 'Keamanan Bekerja Berdasarkan SMK3', __('nc.open')]);
});

test('the excel cell holds one representative photo, skipping photos whose file is missing', function () {
    $photos = ['uploads/testing/foto-'.uniqid().'.jpg', 'uploads/testing/foto-'.uniqid().'.jpg'];
    @mkdir(public_path('uploads/testing'), 0777, true);
    foreach ($photos as $photo) {
        imagejpeg(imagecreatetruecolor(800, 600), public_path($photo));
    }

    try {
        $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
        foreach ($photos as $photo) {
            $finding->photos()->create(['field' => 'foto_temuan', 'path' => $photo]);
        }
        $missingFirst = NonConformity::factory()->create(['pabrik' => 'ckp']);
        $missingFirst->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/testing/hilang.jpg']);
        $missingFirst->photos()->create(['field' => 'foto_temuan', 'path' => $photos[1]]);

        $sheet = exportedWorkbook($this->actingAs($this->auditor)
            ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['id', 'foto_temuan', 'foto_perbaikan'], 'sort' => 'id'])))
            ->getSheet(1);

        $drawings = iterator_to_array($sheet->getDrawingCollection());
        expect(array_map(fn ($drawing) => $drawing->getCoordinates(), $drawings))->toBe(['B2', 'B3'])
            ->and($sheet->getCell('C2')->getValue())->toBe('-');
    } finally {
        foreach ($photos as $photo) {
            @unlink(public_path($photo));
        }
    }
});

test('the pdf export holds one representative photo per field', function () {
    $photos = ['uploads/testing/foto-'.uniqid().'.jpg', 'uploads/testing/foto-'.uniqid().'.jpg'];
    @mkdir(public_path('uploads/testing'), 0777, true);
    foreach ($photos as $photo) {
        imagejpeg(imagecreatetruecolor(400, 300), public_path($photo));
    }
    $images = fn () => substr_count($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'pdf', 'columns' => ['id', 'foto_temuan', 'foto_perbaikan']]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->getContent(), '/Subtype /Image');

    try {
        $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
        // images in the file without any photo, such as the logo
        $base = $images();

        foreach ($photos as $photo) {
            $finding->photos()->create(['field' => 'foto_perbaikan', 'path' => $photo]);
        }

        expect($images())->toBe($base + 1);
    } finally {
        foreach ($photos as $photo) {
            @unlink(public_path($photo));
        }
    }
});

test('the summary sheet scores every department like the report template', function () {
    foreach (range(1, 8) as $i) {
        // OIL: 8 findings, 6 closed (75% -> score 4); clauses 6.1.1 (Minor) and 6.1.2 (Major)
        NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'status' => $i <= 6, 'kategori' => $i <= 4 ? 'Minor' : 'Major', 'no_klausul' => $i <= 4 ? '6.1.1' : '6.1.2']);
    }
    NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'QC', 'status' => false, 'kategori' => 'Minor', 'no_klausul' => '6.1.1']);
    foreach (range(1, 10) as $i) {
        App\Models\Smk3Criterion::create(['nomor' => $i, 'no_kriteria' => "6.9.{$i}", 'kriteria' => 'K', 'minor' => true, 'major' => true, 'kritis' => false]);
    }

    $workbook = exportedWorkbook($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['id']])));
    $rows = $workbook->getSheet(0)->toArray(null, true, false, false);

    expect($rows[0][0])->toBe(__('nc.columns.department'))
        // Departemen, Temuan, Closed, Open, %Closed, Score, Minor, Major, Kritis, Total NC, Total Kriteria, % Ach, Score, Total Score
        ->and($rows[1])->toEqual(['Produksi Minyak', 8, 6, 2, 75, 4, 1, 1, 0, 2, 10, 80, 3, 7])
        ->and($rows[2])->toEqual(['Quality Control', 1, 0, 1, 0, 1, 1, 0, 0, 1, 10, 90, 3, 4])
        // single plant: the total row is named after it, like "FID PWK" in the template
        ->and($rows[3])->toEqual(['Cikampek (CKP)', 9, 6, 3, 66.67, 3, 1, 1, 0, 2, 10, 80, 3, 6]);
});

test('the export follows the filters and the plants the user may see', function () {
    NonConformity::factory()->closed()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Closed CKP']);
    NonConformity::factory()->open()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Open CKP']);
    NonConformity::factory()->closed()->create(['pabrik' => 'psr', 'deskripsi_temuan' => 'Closed PSR']);

    $rows = exportedRows($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'status' => 'closed', 'columns' => ['deskripsi_temuan']])));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Closed CKP']);
});

test('the export can be limited to the ticked rows', function () {
    $picked = NonConformity::factory()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Dipilih']);
    NonConformity::factory()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Tidak dipilih']);

    $rows = exportedRows($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['deskripsi_temuan'], 'ids' => [$picked->id]])));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Dipilih']);
});

test('pdf export downloads a pdf file', function () {
    NonConformity::factory()->count(3)->create(['pabrik' => 'ckp']);

    $response = $this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'pdf', 'status' => 'open', 'columns' => ['id', 'tanggal', 'deskripsi_temuan', 'status']]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('auditees can export the findings of their departments', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'deskripsi_temuan' => 'Temuan OIL']);
    NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'QC', 'deskripsi_temuan' => 'Temuan QC']);

    $rows = exportedRows($this->actingAs($auditee)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['deskripsi_temuan']])));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Temuan OIL']);
});

test('at least one known column is required', function (array $columns) {
    $this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => $columns]))
        ->assertSessionHasErrors();
})->with([
    'none' => [[]],
    'unknown' => [['password']],
]);

test('a background download gets errors as json for the loading toast', function () {
    $this->actingAs($this->auditor)
        ->getJson('/non-conformities/export?'.http_build_query(['format' => 'pdf', 'columns' => ['password']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('columns.0');
});

test('the list offers the export menus with the column choices', function () {
    $this->actingAs($this->auditor)->get('/non-conformities')
        ->assertOk()
        ->assertSee(__('nc.export.chooseColumns'))
        ->assertSee(__('nc.export.picName'));
});

test('excel puts the progress monitoring recap on its own sheet before the findings list', function () {
    NonConformity::factory()->closed()->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'kategori' => 'Minor', 'no_klausul' => '6.1.1', 'tanggal' => '2026-03-10']);
    NonConformity::factory()->open()->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'kategori' => 'Minor', 'no_klausul' => '6.1.2', 'tanggal' => '2026-03-12']);
    NonConformity::factory()->open()->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'kategori' => 'Minor', 'tanggal' => '2025-01-01']);

    $workbook = exportedWorkbook($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['id'], 'progress' => 1, 'dari' => '2026-03-01', 'sampai' => '2026-03-31'])));

    expect($workbook->getSheetCount())->toBe(3)
        ->and($workbook->getSheet(1)->getTitle())->toBe(mb_substr(__('smk3.progressMonitoring'), 0, 31))
        ->and($workbook->getSheet(2)->getTitle())->toBe(mb_substr(__('nc.report.listTitle'), 0, 31));

    $rows = $workbook->getSheet(1)->toArray(null, true, false, false);
    expect($rows[0][0])->toContain('Cikampek')
        ->and($rows[2][2])->toBe(__('pm.columns.total'));
    // OIL is the first department: 2 findings of March, 1 closed, 1 open Minor, no penalty
    expect(array_slice($rows[3], 0, 7))->toEqual([1, 'Produksi Minyak', 2, 1, 1, 50, 1]);
});

test('without the progress option the excel has no progress monitoring sheet', function () {
    NonConformity::factory()->create(['pabrik' => 'ckp']);

    $workbook = exportedWorkbook($this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'xlsx', 'columns' => ['id']])));

    expect($workbook->getSheetCount())->toBe(2);
});

test('the pdf can include the progress monitoring recap', function () {
    NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);

    $this->actingAs($this->auditor)
        ->get('/non-conformities/export?'.http_build_query(['format' => 'pdf', 'columns' => ['id'], 'progress' => 1]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the export menu offers the progress monitoring option', function () {
    $this->actingAs($this->auditor)
        ->get('/non-conformities')
        ->assertSee(__('nc.export.includeProgress'));
});
