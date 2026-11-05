<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;
use App\Services\ProgressMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function finding(string $department, string $category, bool $closed = false, string $clause = '1.1.1', string $plant = 'ckp', ?string $date = null): NonConformity
{
    return NonConformity::factory()->create([
        'pabrik' => $plant,
        'departemen' => $department,
        'kategori' => $category,
        'no_klausul' => $clause,
        'status' => $closed,
        'tanggal' => $date ?? now()->toDateString(),
    ]);
}

function recapRow(array $recap, string $code): array
{
    return collect($recap['rows'])->firstWhere('code', $code);
}

test('the recap scores each department like the legacy dashboard', function () {
    // OIL: only Minor findings on different clauses, 3 of 4 closed
    finding('OIL', 'Minor', true, '1.1.1');
    finding('OIL', 'Minor', true, '1.1.2');
    finding('OIL', 'Minor', true, '1.1.3');
    finding('OIL', 'Minor', false, '1.1.4');
    // BMB: a Major finding
    finding('BMB', 'Major');
    // WHRM: the same clause found as Minor twice counts as a Major
    finding('WHRM', 'Minor', clause: '2.1.1');
    finding('WHRM', 'Minor', clause: '2.1.1');
    // HR: an open Kritis finding
    finding('HR', 'Kritis');

    $recap = app(ProgressMonitor::class)->recap('ckp', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    // the criteria table is empty in tests, so the legacy 166 criteria are used
    expect(recapRow($recap, 'OIL'))->toMatchArray([
        'total' => 4, 'closed' => 3, 'open' => 1, 'closed_percent' => 75.0, 'minor' => 1,
        'score' => 99.4, 'satisfactory' => true, 'ranking' => 87.2,
    ]);
    // BMB: 1 Major weighs 2 points, and the rating stays Poor despite the high Score
    expect(recapRow($recap, 'BMB'))->toMatchArray(['major' => 1, 'score' => 98.8, 'ranking' => 49.4, 'satisfactory' => false]);
    // WHRM: 2 Minor + 1 extra Major = 4 points
    expect(recapRow($recap, 'WHRM'))->toMatchArray(['minor' => 2, 'converted_major' => 1, 'score' => 97.59, 'ranking' => 48.8, 'satisfactory' => false]);
    expect(recapRow($recap, 'HR'))->toMatchArray(['kritis' => 1, 'score' => 0, 'ranking' => 0, 'satisfactory' => false]);
    // no findings: nothing to close and no deviations
    expect(recapRow($recap, 'QC'))->toMatchArray(['total' => 0, 'score' => 100.0, 'ranking' => 100.0, 'satisfactory' => true]);

    expect($recap['rows'])->toHaveCount(count(NonConformity::departmentsFor('ckp')));
    expect($recap['total'])->toMatchArray(['total' => 8, 'closed' => 3, 'open' => 5, 'minor' => 3, 'major' => 1, 'kritis' => 1]);
});

test('closed findings do not count as deviations, but stay in the finding progress', function () {
    // HR: everything closed, so no penalty and no extra Major
    finding('HR', 'Kritis', true);
    finding('HR', 'Major', true);
    finding('HR', 'Minor', true, '2.1.1');
    finding('HR', 'Minor', true, '2.1.1');
    // OIL: only one of the two Minor findings on this clause is still open, so it is no extra Major
    finding('OIL', 'Minor', true, '3.1.1');
    finding('OIL', 'Minor', false, '3.1.1');

    $recap = app(ProgressMonitor::class)->recap('ckp', null, null);

    expect(recapRow($recap, 'HR'))->toMatchArray([
        'total' => 4, 'closed' => 4, 'open' => 0, 'closed_percent' => 100.0,
        'minor' => 0, 'major' => 0, 'kritis' => 0, 'converted_major' => 0, 'converted_clauses' => [],
        'score' => 100.0, 'satisfactory' => true, 'ranking' => 100.0,
    ]);
    expect(recapRow($recap, 'OIL'))->toMatchArray([
        'total' => 2, 'closed' => 1, 'open' => 1, 'minor' => 1, 'converted_major' => 0,
        'score' => 99.4, 'satisfactory' => true, 'ranking' => 74.7,
    ]);
    expect($recap['total'])->toMatchArray(['total' => 6, 'closed' => 5, 'open' => 1, 'minor' => 1, 'major' => 0, 'kritis' => 0]);
});

test('the score weighs a Major twice, combined with the closed percentage in the ranking', function () {
    foreach (range(1, 13) as $number) {
        finding('OIL', 'Minor', clause: "1.1.{$number}");
    }
    foreach (range(1, 3) as $number) {
        finding('OIL', 'Major', clause: "2.1.{$number}");
    }
    foreach (range(1, 4) as $number) {
        finding('OIL', 'Minor', true, "3.1.{$number}");
    }

    expect(recapRow(app(ProgressMonitor::class)->recap('ckp', null, null), 'OIL'))->toMatchArray([
        'minor' => 13, 'major' => 3, 'closed_percent' => 20.0,
        'score' => 88.55, 'ranking' => 54.28, 'satisfactory' => false,
    ]);
});

test('the score does not go below zero', function () {
    App\Models\Smk3Criterion::create(['nomor' => 1, 'no_kriteria' => '1.1.1', 'kriteria' => 'K', 'minor' => true, 'major' => true, 'kritis' => false]);
    finding('OIL', 'Major');

    expect(recapRow(app(ProgressMonitor::class)->recap('ckp', null, null), 'OIL'))
        ->toMatchArray(['score' => 0, 'ranking' => 0, 'satisfactory' => false]);
});

test('the recap lists which clauses make up the extra Major, the most repeated first', function () {
    finding('OIL', 'Minor', clause: '6.4.3');
    finding('OIL', 'Minor', clause: '6.4.3');
    foreach (range(1, 3) as $time) {
        finding('OIL', 'Minor', clause: '6.1.2');
    }
    finding('OIL', 'Minor', clause: '7.1.1');
    // the same clause in another department does not add up
    finding('BMB', 'Minor', clause: '6.1.2');

    $row = recapRow(app(ProgressMonitor::class)->recap('ckp', null, null), 'OIL');

    expect($row['converted_major'])->toBe(2)
        ->and($row['converted_clauses'])->toBe([['clause' => '6.1.2', 'count' => 3], ['clause' => '6.4.3', 'count' => 2]]);
});

test('the info icon explains the extra Major, also after an ajax reload', function (array $headers) {
    finding('OIL', 'Minor', clause: '6.1.2');
    finding('OIL', 'Minor', clause: '6.1.2');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ckp', $headers)
        ->assertOk()
        ->assertSee(__('pm.convertedMajorHelp'))
        ->assertSee(__('pm.convertedMajorInfo', ['min' => ProgressMonitor::MINOR_TO_MAJOR]))
        ->assertSee(__('pm.convertedMajorTimes', ['count' => 2]));

    // once for the one department with a repeated clause, not for the others or the total row
    expect(substr_count($response->getContent(), 'aria-label="'.__('pm.convertedMajorHelp').'"'))->toBe(1);
})->with([
    'full page' => [[]],
    'ajax' => [['X-Partial' => 'results']],
]);

test('the recap only counts findings of the plant and period', function () {
    finding('OIL', 'Minor');
    finding('OIL', 'Minor', plant: 'psr');
    finding('OIL', 'Minor', date: now()->subMonthNoOverflow()->startOfMonth()->toDateString());

    $recap = app(ProgressMonitor::class)->recap('ckp', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    expect(recapRow($recap, 'OIL')['total'])->toBe(1);
});

test('the page shows all data of the first plant by default', function () {
    finding('OIL', 'Minor');
    finding('OIL', 'Minor', date: now()->subMonthNoOverflow()->startOfMonth()->toDateString());

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring')
        ->assertOk()
        ->assertSee('Produksi Minyak')
        ->assertSee(__('pm.groups.progress'))
        ->assertSee(__('pm.guide.title'))
        ->assertSeeText('(166 − (2 + 1 × 2)) / 166 × 100')
        ->assertSee(__('pm.allDates'))
        ->assertViewHas('recap', fn (array $recap) => recapRow($recap, 'OIL')['total'] === 2)
        ->assertViewHas('filters', fn (array $filters) => $filters['pabrik'] === 'ckp'
            && $filters['dari'] === null && $filters['sampai'] === null);
});

test('empty dates show every finding of the plant', function () {
    finding('OIL', 'Minor');
    finding('OIL', 'Minor', date: '2024-01-15');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ckp&dari=&sampai=')
        ->assertOk()
        ->assertSee(__('pm.allDates'))
        ->assertViewHas('recap', fn (array $recap) => recapRow($recap, 'OIL')['total'] === 2);
});

test('the AJAX reload returns only the results', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ing', ['X-Partial' => 'results'])
        ->assertOk()
        ->assertSee('Premix')
        ->assertDontSee(__('pm.guide.title'))
        ->assertDontSee('<html', false);
});

test('the example uses real data of the first department with findings, also after an ajax reload', function (array $headers) {
    finding('OIL', 'Minor', true, '1.1.1');
    finding('OIL', 'Minor', clause: '1.1.2');
    finding('OIL', 'Major', clause: '1.1.3');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ckp', $headers)
        ->assertOk()
        ->assertSee('data-score-example', false)
        ->assertSee(__('pm.guide.exampleCase', ['department' => 'Produksi Minyak', 'total' => 3, 'closed' => 1, 'minor' => 1, 'major' => 1, 'kritis' => 0]))
        ->assertSeeText('(166 − (1 + 1 × 2)) / 166 × 100');
})->with([
    'full page' => [[]],
    'ajax' => [['X-Partial' => 'results']],
]);

test('the example skips a department with an open Kritis when another one shows the formulas', function () {
    finding('OIL', 'Kritis');
    finding('BMB', 'Minor');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ckp')
        ->assertSeeText('(166 − (1 + 0 × 2)) / 166 × 100')
        ->assertDontSee(__('pm.guide.exampleKritis'));
});

test('the example explains the zero when every department with findings has an open Kritis', function () {
    finding('OIL', 'Kritis');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ckp')
        ->assertSee(__('pm.guide.exampleKritis'));
});

test('the example says so when the plant has no findings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring?pabrik=ing', ['X-Partial' => 'results'])
        ->assertSee(__('pm.guide.exampleEmpty'));
});

test('users cannot open the recap of another plant', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']))
        ->get('/progress-monitoring?pabrik=psr')
        ->assertSessionHasErrors('pabrik');
});
