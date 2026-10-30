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
    // HR: a Kritis finding
    finding('HR', 'Kritis', true);

    $recap = app(ProgressMonitor::class)->recap('ckp', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    // the criteria table is empty in tests, so the legacy 166 criteria are used
    expect(recapRow($recap, 'OIL'))->toMatchArray([
        'total' => 4, 'closed' => 3, 'open' => 1, 'closed_percent' => 75.0, 'minor' => 4,
        'score' => 97.59, 'satisfactory' => true, 'ranking' => 86.3,
    ]);
    expect(recapRow($recap, 'BMB'))->toMatchArray(['major' => 1, 'score' => 50, 'ranking' => 50, 'satisfactory' => false]);
    expect(recapRow($recap, 'WHRM'))->toMatchArray(['minor' => 2, 'converted_major' => 1, 'score' => 50, 'ranking' => 50]);
    expect(recapRow($recap, 'HR'))->toMatchArray(['kritis' => 1, 'score' => 0, 'ranking' => 0, 'satisfactory' => false]);
    // no findings: nothing to close and no deviations
    expect(recapRow($recap, 'QC'))->toMatchArray(['total' => 0, 'score' => 100.0, 'ranking' => 100.0, 'satisfactory' => true]);

    expect($recap['rows'])->toHaveCount(count(NonConformity::departmentsFor('ckp')));
    expect($recap['total'])->toMatchArray(['total' => 8, 'closed' => 4, 'open' => 4, 'minor' => 6, 'major' => 1, 'kritis' => 1]);
});

test('the recap only counts findings of the plant and period', function () {
    finding('OIL', 'Minor');
    finding('OIL', 'Minor', plant: 'psr');
    finding('OIL', 'Minor', date: now()->subMonthNoOverflow()->startOfMonth()->toDateString());

    $recap = app(ProgressMonitor::class)->recap('ckp', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

    expect(recapRow($recap, 'OIL')['total'])->toBe(1);
});

test('the page shows the current month of the first plant by default', function () {
    finding('OIL', 'Minor');
    finding('OIL', 'Minor', date: now()->subMonthNoOverflow()->startOfMonth()->toDateString());

    $this->actingAs(User::factory()->admin()->create())
        ->get('/progress-monitoring')
        ->assertOk()
        ->assertSee('Produksi Minyak')
        ->assertSee(__('pm.groups.progress'))
        ->assertSee(__('pm.guide.title'))
        ->assertSeeText('(166 − 4) / 166 × 100')
        ->assertViewHas('recap', fn (array $recap) => recapRow($recap, 'OIL')['total'] === 1)
        ->assertViewHas('filters', fn (array $filters) => $filters['pabrik'] === 'ckp'
            && $filters['dari'] === now()->startOfMonth()->toDateString());
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

test('users cannot open the recap of another plant', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']))
        ->get('/progress-monitoring?pabrik=psr')
        ->assertSessionHasErrors('pabrik');
});
