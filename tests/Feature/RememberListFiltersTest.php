<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function filterAuditor(string $plant = 'ckp'): User
{
    return User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => $plant]);
}

test('the filters are back after adding a finding, together with the saved message', function () {
    $auditor = filterAuditor();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'TEKNIK']);
    Smk3Criterion::firstOrCreate(['no_kriteria' => '1.2.3'], ['nomor' => 1, 'kriteria' => 'K3', 'minor' => true, 'major' => true, 'kritis' => false]);
    NonConformity::factory()->closed()->create(['deskripsi_temuan' => 'Temuan closed', 'pabrik' => 'ckp']);
    NonConformity::factory()->open()->create(['deskripsi_temuan' => 'Temuan open', 'pabrik' => 'ckp']);

    $this->actingAs($auditor)->get('/non-conformities?status=closed')->assertOk();

    $this->followingRedirects()
        ->post('/non-conformities', [
            'tanggal' => '2026-10-29', 'pabrik' => 'ckp', 'departemen' => 'TEKNIK', 'deskripsi_temuan' => 'Panel terbuka',
            'no_klausul' => '1.2.3', 'kategori' => 'Minor', 'pic' => $pic->email,
        ])
        ->assertOk()
        ->assertSee(__('nc.created'))
        ->assertSee('Temuan closed')
        ->assertDontSee('Temuan open');
});

test('the filters are back after updating a finding', function () {
    $auditor = filterAuditor();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'TEKNIK']);
    Smk3Criterion::firstOrCreate(['no_kriteria' => '1.2.3'], ['nomor' => 1, 'kriteria' => 'K3', 'minor' => true, 'major' => true, 'kritis' => false]);
    $finding = NonConformity::factory()->open()->create(['pabrik' => 'ckp']);
    NonConformity::factory()->open()->count(30)->create(['pabrik' => 'ckp']);

    $this->actingAs($auditor)->get('/non-conformities?status=open&page=2&per_page=25')->assertOk();
    $this->get("/non-conformities/{$finding->id}/edit")->assertOk();

    $this->put("/non-conformities/{$finding->id}", [
        'tanggal' => '2026-10-29', 'pabrik' => 'ckp', 'departemen' => 'TEKNIK', 'deskripsi_temuan' => 'Sudah diedit',
        'no_klausul' => '1.2.3', 'kategori' => 'Minor', 'pic' => $pic->email, 'status' => 0,
    ])->assertRedirect(route('non-conformities'))->assertSessionHas('success');

    $this->get('/non-conformities')
        ->assertRedirect(route('non-conformities', ['status' => 'open', 'page' => 2, 'per_page' => 25]));
});

test('a query in the url replaces the remembered filters', function () {
    $this->actingAs(filterAuditor())->get('/non-conformities?status=closed')->assertOk();
    $this->get('/non-conformities?kategori=Major')->assertOk();

    $this->get('/non-conformities')->assertRedirect(route('non-conformities', ['kategori' => 'Major']));
});

test('clearing the filters forgets them', function () {
    $this->actingAs(filterAuditor())->get('/non-conformities?status=closed')->assertOk();

    // the reset button reloads the results without a query
    $this->get('/non-conformities', ['X-Partial' => 'results'])->assertOk();

    $this->get('/non-conformities')->assertOk();
});

test('every page keeps its own filters', function () {
    $this->actingAs(filterAuditor())->get('/non-conformities?status=closed')->assertOk();
    $this->get('/progress-monitoring?pabrik=ckp&dari=2026-01-01&sampai=2026-01-31')->assertOk();
    $this->get('/master/clauses?element=6')->assertOk();

    $this->get('/non-conformities')->assertRedirect(route('non-conformities', ['status' => 'closed']));
    $this->get('/progress-monitoring')->assertRedirect(route('progress-monitoring', ['pabrik' => 'ckp', 'dari' => '2026-01-01', 'sampai' => '2026-01-31']));
    $this->get('/master/clauses')->assertRedirect(route('master.clauses', ['element' => 6]));
});

test('filters are not shared between sessions of different users', function () {
    $this->actingAs(filterAuditor())->get('/non-conformities?status=closed')->assertOk();

    $this->post('/logout');
    $this->actingAs(filterAuditor())->get('/non-conformities')->assertOk();
});

test('remembered filters that are no longer valid do not keep the page from opening', function () {
    $auditor = filterAuditor();
    NonConformity::factory()->create(['deskripsi_temuan' => 'Temuan PSR', 'pabrik' => 'psr']);
    $this->actingAs($auditor)->get('/non-conformities?pabrik=ckp')->assertOk();

    $auditor->update(['pabrik' => 'psr']);

    $this->followingRedirects()->get('/non-conformities')->assertOk()->assertSee('Temuan PSR');
    $this->get('/non-conformities')->assertOk();
});

test('an invalid ajax filter does not drop the filters that still work', function () {
    $this->actingAs(filterAuditor())->get('/progress-monitoring?pabrik=ckp')->assertOk();

    $this->get('/progress-monitoring?pabrik=ckp&dari=2026-02-01&sampai=2026-01-01', ['X-Partial' => 'results'])->assertRedirect();

    $this->get('/progress-monitoring')->assertRedirect(route('progress-monitoring', ['pabrik' => 'ckp']));
});

test('a remembered page that no longer exists falls back to the last page', function () {
    $auditor = filterAuditor();
    NonConformity::factory()->count(11)->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Temuan sisa']);

    $this->actingAs($auditor)->get('/non-conformities?page=2')->assertOk();
    NonConformity::query()->latest('id')->first()->delete();
    NonConformity::query()->oldest('tanggal')->oldest('id')->first()->delete();

    $this->get('/non-conformities')->assertRedirect(route('non-conformities', ['page' => 2]));
    $this->get('/non-conformities?page=2')->assertRedirect(route('non-conformities', ['page' => 1]));
    $this->get('/non-conformities?page=1')->assertOk()->assertSee('Temuan sisa');
});
