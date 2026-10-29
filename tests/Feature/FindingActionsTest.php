<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('uploads');
    $this->auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);
});

function storedPhoto(string $folder): string
{
    Storage::disk('uploads')->put("{$folder}/foto.jpg", 'x');

    return "uploads/{$folder}/foto.jpg";
}

test('ticked findings can be closed and reopened together', function () {
    $findings = NonConformity::factory()->count(2)->open()->create(['pabrik' => 'ckp']);
    $other = NonConformity::factory()->open()->create(['pabrik' => 'ckp']);

    $this->actingAs($this->auditor)
        ->postJson('/non-conformities/bulk', ['action' => 'closed', 'ids' => $findings->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('message', __('nc.bulk.statusChanged', ['count' => 2, 'status' => __('nc.closed')]));

    expect($findings->every(fn ($finding) => $finding->fresh()->isClosed()))->toBeTrue()
        ->and($other->fresh()->isClosed())->toBeFalse();

    $this->actingAs($this->auditor)->postJson('/non-conformities/bulk', ['action' => 'open', 'ids' => [$findings[0]->id]])->assertOk();
    expect($findings[0]->fresh()->isClosed())->toBeFalse();
});

test('bulk delete removes the findings and their photos, but only those the user may see', function () {
    $mine = NonConformity::factory()->create(['pabrik' => 'ckp', 'foto_temuan' => storedPhoto('foto_temuan')]);
    $otherPlant = NonConformity::factory()->create(['pabrik' => 'psr']);

    $this->actingAs($this->auditor)
        ->postJson('/non-conformities/bulk', ['action' => 'delete', 'ids' => [$mine->id, $otherPlant->id]])
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($mine->fresh())->toBeNull()
        ->and($otherPlant->fresh())->not->toBeNull();
    Storage::disk('uploads')->assertMissing('foto_temuan/foto.jpg');
});

test('auditees cannot use bulk actions, edit or delete', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);

    $this->actingAs($auditee)->postJson('/non-conformities/bulk', ['action' => 'closed', 'ids' => [$finding->id]])->assertForbidden();
    $this->actingAs($auditee)->get("/non-conformities/{$finding->id}/edit")->assertForbidden();
    $this->actingAs($auditee)->deleteJson("/non-conformities/{$finding->id}")->assertForbidden();
});

test('one finding can be deleted from its row', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);

    $this->actingAs($this->auditor)->deleteJson("/non-conformities/{$finding->id}")
        ->assertOk()
        ->assertJsonPath('message', __('nc.deleted', ['count' => 1]));

    expect($finding->fresh())->toBeNull();
});

test('findings of another plant cannot be edited', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'psr']);

    $this->actingAs($this->auditor)->get("/non-conformities/{$finding->id}/edit")->assertNotFound();
});

test('a finding can be edited, including its status and repair photo', function () {
    Smk3Criterion::create(['nomor' => 1, 'no_kriteria' => '6.5.3', 'kriteria' => 'Sertifikat peralatan', 'minor' => true, 'major' => false, 'kritis' => false]);
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $finding = NonConformity::factory()->open()->create(['pabrik' => 'ckp', 'foto_perbaikan' => storedPhoto('foto_perbaikan')]);

    $this->actingAs($this->auditor)->get("/non-conformities/{$finding->id}/edit")
        ->assertOk()
        ->assertSee(__('nc.edit.title', ['id' => $finding->id]))
        ->assertDontSee(__('nc.create.saveAndAnother'));

    $this->actingAs($this->auditor)
        ->put("/non-conformities/{$finding->id}", [
            'tanggal' => '2026-10-20', 'pabrik' => 'ckp', 'departemen' => 'OIL', 'deskripsi_temuan' => 'Sertifikat forklift habis',
            'no_klausul' => '6.5.3', 'kategori' => 'Minor', 'pic' => $pic->email, 'status' => '1',
            'foto_perbaikan' => UploadedFile::fake()->image('perbaikan.jpg'),
        ])
        ->assertRedirect(route('non-conformities'))
        ->assertSessionHas('success', __('nc.updated', ['id' => $finding->id]));

    $finding->refresh();
    expect($finding->deskripsi_temuan)->toBe('Sertifikat forklift habis')
        ->and($finding->isClosed())->toBeTrue()
        ->and($finding->foto_perbaikan)->not->toBe('uploads/foto_perbaikan/foto.jpg');
    Storage::disk('uploads')->assertMissing('foto_perbaikan/foto.jpg');
});

test('a photo can be uploaded straight from the table', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp', 'foto_temuan' => null]);

    $this->actingAs($this->auditor)
        ->post("/non-conformities/{$finding->id}/photos/foto_temuan", ['photo' => UploadedFile::fake()->image('temuan.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('message', __('nc.photoSaved', ['id' => $finding->id]));

    expect($finding->fresh()->foto_temuan)->toStartWith('uploads/foto_temuan/');
});

test('auditees may upload only the repair photo of their own findings', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $own = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);
    $otherDepartment = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'QC']);
    $photo = fn () => ['photo' => UploadedFile::fake()->image('foto.jpg')];

    $this->actingAs($auditee)->post("/non-conformities/{$own->id}/photos/foto_perbaikan", $photo(), ['Accept' => 'application/json'])->assertOk();
    $this->actingAs($auditee)->post("/non-conformities/{$own->id}/photos/foto_temuan", $photo(), ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($auditee)->post("/non-conformities/{$otherDepartment->id}/photos/foto_perbaikan", $photo(), ['Accept' => 'application/json'])->assertNotFound();

    expect($own->fresh()->foto_perbaikan)->toStartWith('uploads/foto_perbaikan/');
});

test('photos are linked to open in a new tab instead of shown as thumbnails', function () {
    NonConformity::factory()->create(['pabrik' => 'ckp', 'foto_temuan' => 'uploads/foto_temuan/a.jpg']);

    $this->actingAs($this->auditor)->get('/non-conformities')
        ->assertSee('target="_blank"', false)
        ->assertSee(__('nc.viewPhoto'))
        ->assertDontSee('<img src="'.asset('uploads/foto_temuan/a.jpg').'"', false);
});
