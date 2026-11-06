<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use App\Services\DashboardSummary;
use App\Services\ProgressMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('every role is sent from the home page to the dashboard', function (Role $role) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->get('/')
        ->assertRedirect('/dashboard');
})->with([Role::Admin, Role::Auditor, Role::Auditee]);

test('every role can view the dashboard greeting', function (Role $role) {
    $user = User::factory()->create(['otorisasi' => $role, 'name' => 'Budi Santoso']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee(__('dashboard.welcome'));
})->with([Role::Admin, Role::Auditor, Role::Auditee]);

function dashboardFinding(string $department, ?string $category = 'Minor', bool $closed = false, string $date = '2026-03-10', string $plant = 'ckp', ?string $pic = null): NonConformity
{
    return NonConformity::factory()->create([
        'pabrik' => $plant,
        'departemen' => $department,
        'kategori' => $category,
        'status' => $closed,
        'tanggal' => $date,
        'pic' => $pic,
    ]);
}

/** Maps series name => data of a stacked chart. */
function chartSeries(array $chart): array
{
    return collect($chart['config']['series'])->pluck('data', 'name')->all();
}

test('admins and auditors see every department of the plant', function (Role $role) {
    dashboardFinding('OIL', 'Major');
    dashboardFinding('QC', 'Minor');

    $user = User::factory()->create(['otorisasi' => $role, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $summary = app(DashboardSummary::class)->build($user, 'ckp', null, null);

    $departments = array_values(NonConformity::departmentsFor('ckp'));
    expect($summary['score']['config']['categories'])->toEqualCanonicalizing($departments);
    expect($summary['open']['config']['categories'])->toEqualCanonicalizing($departments);
})->with([Role::Admin, Role::Auditor]);

test('auditees only see their own departments and the findings they are the pic of', function () {
    $user = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL,BMB']);

    dashboardFinding('OIL', 'Minor');
    dashboardFinding('QC', 'Major', pic: $user->email);
    // someone else's department
    dashboardFinding('HR', 'Kritis');

    $summary = app(DashboardSummary::class)->build($user, 'ckp', null, null);

    expect($summary['score']['config']['categories'])->toEqualCanonicalizing(['Produksi Minyak', 'Produksi Bumbu']);
    expect($summary['open']['config']['categories'])->toEqualCanonicalizing(['Produksi Minyak', 'Produksi Bumbu', 'Quality Control']);
    expect(array_sum(chartSeries($summary['open'])['Kritis']))->toBe(0);
    expect(array_sum(chartSeries($summary['aging'])['Kritis']))->toBe(0);
    expect(array_sum(chartSeries($summary['trend'])[__('nc.open')]))->toBe(2);
});

test('an auditee page does not name other departments', function () {
    $user = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    dashboardFinding('HR', 'Kritis');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Produksi Minyak')
        ->assertDontSee('Quality Control');
});

test('the score chart matches progress monitoring and lists the lowest first', function () {
    dashboardFinding('HR', 'Kritis');
    dashboardFinding('OIL', 'Major');

    $user = User::factory()->admin()->create();
    $score = app(DashboardSummary::class)->build($user, 'ckp', null, null)['score']['config'];
    $recap = collect(app(ProgressMonitor::class)->recap('ckp', null, null)['rows']);

    expect($score['categories'][0])->toBe('HR (FASUM, LINGKUNGAN, OFFICE)');
    expect($score['values'][0])->toEqual(0);
    expect($score['categories'][1])->toBe('Produksi Minyak');
    expect($score['values'][1])->toEqual($recap->firstWhere('code', 'OIL')['score']);
    expect($score['satisfactory'][1])->toBeFalse();
    expect($score['target'])->toBe(ProgressMonitor::SATISFACTORY);
});

test('open findings per department count open findings only, most severe first', function () {
    dashboardFinding('OIL', 'Minor');
    dashboardFinding('OIL', 'Minor');
    dashboardFinding('OIL', 'Minor', closed: true);
    dashboardFinding('QC', 'Major');
    dashboardFinding('HR', 'Kritis');
    // a department not listed for the plant is left out, as in the recap
    dashboardFinding('UNKNOWN', 'Kritis');

    $open = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['open'];
    $series = chartSeries($open);

    expect(array_slice($open['config']['categories'], 0, 3))->toBe(['HR (FASUM, LINGKUNGAN, OFFICE)', 'Quality Control', 'Produksi Minyak']);
    expect(array_slice($series['Kritis'], 0, 3))->toBe([1, 0, 0]);
    expect(array_slice($series['Major'], 0, 3))->toBe([0, 1, 0]);
    expect(array_slice($series['Minor'], 0, 3))->toBe([0, 0, 2]);
});

test('findings without a category get their own series only when there are any', function () {
    $admin = User::factory()->admin()->create();
    dashboardFinding('OIL', 'Minor');
    expect(chartSeries(app(DashboardSummary::class)->build($admin, 'ckp', null, null)['open']))
        ->not->toHaveKey(__('dashboard.uncategorized'));

    dashboardFinding('OIL', null);
    $summary = app(DashboardSummary::class)->build($admin, 'ckp', null, null);
    expect(array_sum(chartSeries($summary['open'])[__('dashboard.uncategorized')]))->toBe(1);
    expect(array_sum(chartSeries($summary['aging'])[__('dashboard.uncategorized')]))->toBe(1);
});

test('the trend fills months without findings and splits closed from open', function () {
    dashboardFinding('OIL', closed: true, date: '2026-01-15');
    dashboardFinding('OIL', date: '2026-01-20');
    dashboardFinding('OIL', date: '2026-03-02');

    $trend = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['trend'];

    expect($trend['config']['categories'])->toHaveCount(3);
    expect(chartSeries($trend)[__('nc.closed')])->toBe([1, 0, 0]);
    expect(chartSeries($trend)[__('nc.open')])->toBe([1, 0, 1]);
});

test('open findings are grouped by age in the plant timezone, closed ones are left out', function () {
    // 17:30 UTC is already the next day in Jakarta
    $this->travelTo(Carbon::parse('2026-06-30 17:30:00', 'UTC'));

    dashboardFinding('OIL', 'Minor', date: '2026-07-01');
    dashboardFinding('OIL', 'Minor', date: '2026-06-01'); // 30 days
    dashboardFinding('OIL', 'Major', date: '2026-05-31'); // 31 days
    dashboardFinding('OIL', 'Major', date: '2026-04-02'); // 90 days
    dashboardFinding('OIL', 'Kritis', date: '2026-04-01'); // 91 days
    dashboardFinding('OIL', 'Kritis', closed: true, date: '2025-01-01');

    $aging = chartSeries(app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['aging']);

    expect($aging['Minor'])->toBe([2, 0, 0, 0]);
    expect($aging['Major'])->toBe([0, 1, 1, 0]);
    expect($aging['Kritis'])->toBe([0, 0, 0, 1]);
});

test('the charts follow the plant and period filters', function () {
    dashboardFinding('OIL', date: '2026-01-15');
    dashboardFinding('OIL', date: '2026-03-15');
    dashboardFinding('OIL', date: '2026-03-15', plant: 'psr');

    $trend = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', '2026-03-01', '2026-03-31')['trend'];

    expect(chartSeries($trend)[__('nc.open')])->toBe([1]);
});

test('a plant outside the user access is rejected', function () {
    $user = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);

    $this->actingAs($user)->getJson('/dashboard?pabrik=psr')->assertUnprocessable();
});

test('a filter change only returns the charts', function () {
    dashboardFinding('OIL');

    $this->actingAs(User::factory()->admin()->create())
        ->withHeader('X-Partial', 'results')
        ->get('/dashboard?pabrik=ckp')
        ->assertOk()
        ->assertSee('data-dashboard-chart="smk3-score"', false)
        ->assertDontSee(__('dashboard.welcome'));
});

test('repeated clauses list what the recap counts as extra majors, most frequent first', function () {
    $repeat = fn (string $department, string $clause, int $times) => NonConformity::factory()->count($times)->create([
        'pabrik' => 'ckp', 'departemen' => $department, 'kategori' => 'Minor', 'no_klausul' => $clause, 'status' => false, 'tanggal' => '2026-03-10',
    ]);
    $repeat('OIL', '1.1.1', 2);
    $repeat('QC', '2.2.2', 3);
    // once only, so not repeated
    $repeat('OIL', '3.3.3', 1);
    // repeated, but one is closed: only one is still open
    $repeat('HR', '4.4.4', 1);
    NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'HR', 'kategori' => 'Minor', 'no_klausul' => '4.4.4', 'status' => true, 'tanggal' => '2026-03-10']);
    Smk3Criterion::create(['nomor' => 1, 'no_kriteria' => '2.2.2', 'kriteria' => 'Teks kriteria']);

    $repeated = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['repeated'];

    expect($repeated['empty'])->toBeFalse();
    expect($repeated['config']['categories'])->toBe(['2.2.2 · Quality Control', '1.1.1 · Produksi Minyak']);
    expect($repeated['config']['series'][0]['data'])->toBe([3, 2]);
    expect($repeated['table']['rows'][0][0])->toBe(['text' => '2.2.2', 'description' => 'Teks kriteria']);
    expect($repeated['table']['rows'][1][0])->toBe(['text' => '1.1.1', 'description' => null]);
});

test('the repeated clause chart stops at the limit but the table lists them all', function () {
    foreach (range(1, DashboardSummary::REPEATED_CHART_LIMIT + 1) as $number) {
        NonConformity::factory()->count(2)->create(['pabrik' => 'ckp', 'departemen' => 'OIL', 'kategori' => 'Minor', 'no_klausul' => "1.1.{$number}", 'status' => false]);
    }

    $repeated = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['repeated'];

    expect($repeated['config']['categories'])->toHaveCount(DashboardSummary::REPEATED_CHART_LIMIT);
    expect($repeated['table']['rows'])->toHaveCount(DashboardSummary::REPEATED_CHART_LIMIT + 1);
});

test('auditees only see repeated clauses of their own departments', function () {
    $user = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    foreach (['OIL' => '1.1.1', 'QC' => '2.2.2'] as $department => $clause) {
        NonConformity::factory()->count(2)->create(['pabrik' => 'ckp', 'departemen' => $department, 'kategori' => 'Minor', 'no_klausul' => $clause, 'status' => false]);
    }

    $repeated = app(DashboardSummary::class)->build($user, 'ckp', null, null)['repeated'];

    expect($repeated['config']['categories'])->toBe(['1.1.1 · Produksi Minyak']);
});

test('a plant without repeated clauses says so', function () {
    dashboardFinding('OIL');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard?pabrik=ckp')
        ->assertOk()
        ->assertSee(__('dashboard.repeated.empty'));
});

test('repair photo progress splits open findings by whether a repair photo exists', function () {
    $withPhoto = dashboardFinding('OIL');
    $withPhoto->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/a.jpg']);
    // a finding photo is not a repair photo
    dashboardFinding('OIL')->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/b.jpg']);
    dashboardFinding('OIL');
    dashboardFinding('QC');
    // closed findings are done
    dashboardFinding('QC', closed: true)->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/c.jpg']);

    $photos = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null)['repairPhotos'];
    $series = chartSeries($photos);

    expect(array_slice($photos['config']['categories'], 0, 2))->toBe(['Produksi Minyak', 'Quality Control']);
    expect(array_slice($series[__('dashboard.repairPhotos.noPhoto')], 0, 2))->toBe([2, 1]);
    expect(array_slice($series[__('dashboard.repairPhotos.withPhoto')], 0, 2))->toBe([1, 0]);
});

test('auditees see repair photo progress of their visible findings only', function () {
    $user = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    dashboardFinding('OIL');
    dashboardFinding('HR');

    $photos = app(DashboardSummary::class)->build($user, 'ckp', null, null)['repairPhotos'];

    expect($photos['config']['categories'])->toBe(['Produksi Minyak']);
    expect(chartSeries($photos)[__('dashboard.repairPhotos.noPhoto')])->toBe([1]);
});

test('the table view opens in a dialog with the rating as a badge', function () {
    dashboardFinding('OIL', 'Major');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard?pabrik=ckp')
        ->assertOk()
        ->assertSee(__('dashboard.showTable'))
        ->assertSee(__('dashboard.closeTable'))
        ->assertSee('data-slot="dialog-content"', false)
        ->assertSee('data-slot="badge"', false)
        ->assertDontSee('<details', false);
});

test('table bars are sized against the highest value of the column', function () {
    dashboardFinding('OIL');
    dashboardFinding('OIL');
    dashboardFinding('QC');

    $table = app(DashboardSummary::class)->build(User::factory()->admin()->create(), 'ckp', null, null);
    $bars = fn (array $chart) => collect($chart['table']['rows'])->map(fn (array $row) => end($row))->take(2)->all();

    expect($bars($table['open']))->toBe([['text' => 2, 'bar' => 100], ['text' => 1, 'bar' => 50]]);
    expect($bars($table['repairPhotos']))->toBe([['text' => 2, 'bar' => 100], ['text' => 1, 'bar' => 50]]);
});

test('a plant without findings shows the empty message instead of empty charts', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard?pabrik=ckp')
        ->assertOk()
        ->assertSee(__('dashboard.noData'))
        ->assertDontSee('data-dashboard-chart="smk3-stacked"', false);
});

test('the old admin dashboard url redirects to the dashboard', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee]))
        ->get('/admin/dashboard')
        ->assertRedirect('/dashboard');
});

test('template demo pages are no longer reachable', function (string $path) {
    $this->actingAs(User::factory()->admin()->create())
        ->get($path)
        ->assertNotFound();
})->with(['/ecommerce', '/calendar', '/profile', '/basic-tables', '/buttons']);

test('dashboard redirects keep the subfolder the app is served from', function (string $from) {
    $this->actingAs(User::factory()->create())
        ->withServerVariables(['SCRIPT_NAME' => '/smk3/index.php', 'SCRIPT_FILENAME' => public_path('index.php')])
        ->get("http://10.126.27.187/smk3{$from}")
        ->assertHeader('Location', 'http://10.126.27.187/smk3/dashboard');
})->with(['/admin/dashboard']);
