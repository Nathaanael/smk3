<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('all data is listed when no filter is set', function () {
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan lama', 'tanggal' => '2024-01-15', 'pabrik' => 'psr']);
    NonConformity::factory()->closed()->create(['deskripsi_temuan' => 'Temuan closed', 'pabrik' => 'ckp']);
    NonConformity::factory()->open()->create(['deskripsi_temuan' => 'Temuan open', 'pabrik' => 'ing']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities')
        ->assertOk()
        ->assertSee('Temuan lama')
        ->assertSee('Temuan closed')
        ->assertSee('Temuan open');
});

test('non-admins only see findings from their own plant', function () {
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan CKP', 'pabrik' => 'ckp']);
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan PSR', 'pabrik' => 'psr']);

    $this->actingAs(User::factory()->create(['pabrik' => 'ckp']))
        ->get('/non-conformities')
        ->assertSee('Temuan CKP')
        ->assertDontSee('Temuan PSR');
});

test('users assigned to ckp,ing see both plants', function () {
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan CKP', 'pabrik' => 'ckp']);
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan ING', 'pabrik' => 'ing']);
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan SMG', 'pabrik' => 'smg']);

    $this->actingAs(User::factory()->create(['pabrik' => 'ckp,ing']))
        ->get('/non-conformities')
        ->assertSee('Temuan CKP')
        ->assertSee('Temuan ING')
        ->assertDontSee('Temuan SMG');
});

test('findings can be filtered', function (array $query, string $expected, string $hidden) {
    NonConformity::factory()->open()->create([
        'deskripsi_temuan' => 'Kabel terkelupas', 'pabrik' => 'ckp', 'departemen' => 'TEKNIK',
        'kategori' => 'Major', 'tanggal' => '2026-03-10', 'pic' => 'Budi',
    ]);
    NonConformity::factory()->closed()->create([
        'deskripsi_temuan' => 'APAR kedaluwarsa', 'pabrik' => 'psr', 'departemen' => 'HR',
        'kategori' => 'Minor', 'tanggal' => '2026-08-20', 'pic' => 'Sari',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities?'.http_build_query($query))
        ->assertOk()
        ->assertSee($expected)
        ->assertDontSee($hidden);
})->with([
    'status' => [['status' => 'closed'], 'APAR kedaluwarsa', 'Kabel terkelupas'],
    'plant' => [['pabrik' => 'ckp'], 'Kabel terkelupas', 'APAR kedaluwarsa'],
    'department' => [['departemen' => 'HR'], 'APAR kedaluwarsa', 'Kabel terkelupas'],
    'category' => [['kategori' => 'Major'], 'Kabel terkelupas', 'APAR kedaluwarsa'],
    'date range' => [['dari' => '2026-08-01', 'sampai' => '2026-08-31'], 'APAR kedaluwarsa', 'Kabel terkelupas'],
    'search' => [['q' => 'Budi'], 'Kabel terkelupas', 'APAR kedaluwarsa'],
]);

test('users cannot filter on a plant they have no access to', function () {
    $this->actingAs(User::factory()->create(['pabrik' => 'ckp']))
        ->get('/non-conformities?pabrik=psr')
        ->assertSessionHasErrors('pabrik');
});

test('results are paginated', function () {
    NonConformity::factory()->count(15)->create(['pabrik' => 'ckp']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities')
        ->assertSee(__('nc.showing', ['from' => 1, 'to' => 10, 'total' => 15]))
        ->assertSee('page=2', false);
});

test('an empty state is shown when nothing matches', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities')
        ->assertSee(__('nc.empty'));
});

function findingPayload(array $overrides = []): array
{
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp']);

    return array_merge([
        'tanggal' => '2026-10-29',
        'pabrik' => 'ckp',
        'departemen' => 'TEKNIK',
        'deskripsi_temuan' => 'Panel listrik tidak tertutup',
        'no_klausul' => '1.2.3',
        'kategori' => 'Major',
        'pic' => $pic->email,
    ], $overrides);
}

test('auditors can add a finding with a photo', function () {
    Storage::fake('uploads');
    $auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);

    $this->actingAs($auditor)
        ->post('/non-conformities', findingPayload(['foto_temuan' => UploadedFile::fake()->image('temuan.jpg')]))
        ->assertRedirect(route('non-conformities'))
        ->assertSessionHas('success');

    $finding = NonConformity::sole();
    expect($finding->deskripsi_temuan)->toBe('Panel listrik tidak tertutup')
        ->and($finding->status)->toBeFalse()
        ->and($finding->foto_temuan)->toStartWith('uploads/foto_temuan/');
    Storage::disk('uploads')->assertExists((string) str($finding->foto_temuan)->after('uploads/'));
});

test('auditees cannot add findings', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp']))
        ->post('/non-conformities', findingPayload())
        ->assertForbidden();

    expect(NonConformity::count())->toBe(0);
});

test('only admins and auditors see the add button', function (Role $role, bool $visible) {
    $response = $this->actingAs(User::factory()->create(['otorisasi' => $role, 'pabrik' => 'ckp']))->get('/non-conformities');

    $visible ? $response->assertSee(__('nc.create.button')) : $response->assertDontSee(__('nc.create.button'));
})->with([
    [Role::Admin, true],
    [Role::Auditor, true],
    [Role::Auditee, false],
]);

test('a finding is rejected when it does not fit the users plants', function (array $overrides, string $field) {
    $auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);

    $this->actingAs($auditor)
        ->post('/non-conformities', findingPayload($overrides))
        ->assertSessionHasErrorsIn('createFinding', $field);

    expect(NonConformity::count())->toBe(0);
})->with([
    'other plant' => [['pabrik' => 'psr'], 'pabrik'],
    'department of another plant' => [['departemen' => 'premix'], 'departemen'],
    'pic is not an auditee' => [['pic' => 'nobody@example.com'], 'pic'],
    'unknown category' => [['kategori' => 'Ringan'], 'kategori'],
]);

test('the PIC name is shown instead of the email', function () {
    $pic = User::factory()->create(['name' => 'Rina Auditee', 'otorisasi' => Role::Auditee, 'pabrik' => 'ckp']);
    NonConformity::factory()->create(['pabrik' => 'ckp', 'pic' => $pic->email]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities')
        ->assertSee('Rina Auditee');
});

test('validation messages are in Indonesian by default', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']))
        ->post('/non-conformities', findingPayload(['deskripsi_temuan' => '']));

    expect(session('errors')->getBag('createFinding')->first('deskripsi_temuan'))->toBe('Deskripsi temuan wajib diisi.');
});

test('ajax requests receive only the results fragment', function () {
    NonConformity::factory()->create(['deskripsi_temuan' => 'Tangga licin', 'pabrik' => 'ckp', 'status' => true]);
    NonConformity::factory()->create(['deskripsi_temuan' => 'Lampu mati', 'pabrik' => 'ckp', 'status' => false]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities?status=closed', ['X-Partial' => 'results', 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Tangga licin')
        ->assertDontSee('Lampu mati');

    expect($response->getContent())
        ->not->toContain('<html')
        ->not->toContain(__('nc.searchPlaceholder'))
        ->toContain(__('nc.showing', ['from' => 1, 'to' => 1, 'total' => 1]));
});

test('the filter panel shows how many filters are active', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/non-conformities?status=open&kategori=Major&dari=2026-01-01&sampai=2026-01-31')
        ->assertOk()
        ->assertSee(__('nc.filter'));
});
