<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use App\Services\ClauseSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $clauses = [
        ['6.1.6', 'Alat pelindung diri disediakan sesuai kebutuhan dan digunakan secara benar', null],
        ['6.7.7', 'Jenis, jumlah dan penempatan alat keadaan darurat telah sesuai', null],
        ['6.4.3', 'Lingkungan kerja dipelihara bersih dan rapi', 'tikus, hama, kecoa'],
        ['5.2.1', 'Barang dan jasa yang dibeli diperiksa kesesuaiannya', null],
    ];
    foreach ($clauses as $index => [$number, $text, $keywords]) {
        Smk3Criterion::create(['nomor' => $index + 1, 'no_kriteria' => $number, 'kriteria' => $text, 'kata_kunci' => $keywords, 'minor' => true, 'major' => true, 'kritis' => false]);
    }

    foreach (['Box hydrant terhalang palet', 'Hydrant tertutup barang FG', 'APAR terhalang rak'] as $description) {
        NonConformity::factory()->create(['deskripsi_temuan' => $description, 'no_klausul' => '6.7.7']);
    }
});

function suggestedNumbers(string $description): array
{
    return array_column(app(ClauseSuggester::class)->suggest($description), 'no');
}

test('similar past findings vote for their clause', function () {
    $suggestions = app(ClauseSuggester::class)->suggest('Hydrant terhalang barang di gudang');

    expect($suggestions[0]['no'])->toBe('6.7.7')
        ->and($suggestions[0]['reasons']['similar'])->toBeGreaterThanOrEqual(2);
});

test('keywords find clauses whose text does not contain the word', function (string $description) {
    $suggestions = app(ClauseSuggester::class)->suggest($description);

    expect($suggestions[0]['no'])->toBe('6.4.3')
        ->and($suggestions[0]['reasons']['keywords'])->toBe(['tikus']);
})->with([
    'exact' => 'Ditemukan kotoran tikus di gudang',
    'with -nya' => 'Tikusnya masuk ke area produksi',
    'one typo' => 'ada bekas tikis di lantai',
]);

test('the criterion text is used when nothing else matches', function () {
    expect(suggestedNumbers('Operator tidak memakai alat pelindung diri'))->toContain('6.1.6');
});

test('one shared word in a longer description is not enough for a suggestion', function () {
    expect(suggestedNumbers('Box panel terkunci, barang menumpuk'))->not->toContain('5.2.1')
        ->and(suggestedNumbers('ditemukan di yang'))->toBe([]);
});

test('auditors get suggestions over ajax with a readable reason', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']))
        ->getJson('/non-conformities/clause-suggestions?q='.urlencode('kotoran tikus di gudang'))
        ->assertOk()
        ->assertJsonPath('suggestions.0.no', '6.4.3')
        ->assertJsonPath('suggestions.0.reason', __('nc.suggest.keyword', ['words' => 'tikus']));
});

test('auditees cannot ask for clause suggestions', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee]))
        ->getJson('/non-conformities/clause-suggestions?q=tikus')
        ->assertForbidden();
});
