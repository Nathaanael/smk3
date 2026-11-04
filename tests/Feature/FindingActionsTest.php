<?php

use App\Enums\Role;
use App\Models\FindingPhoto;
use App\Models\NonConformity;
use App\Models\Smk3Criterion;
use App\Models\User;
use App\Services\FindingPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('uploads');
    $this->auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);
});

function storedPhoto(NonConformity $finding, string $field, string $name = 'foto.jpg'): FindingPhoto
{
    Storage::disk('uploads')->put("{$field}/{$name}", 'x');

    return $finding->photos()->create(['field' => $field, 'path' => "uploads/{$field}/{$name}"]);
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
    $mine = NonConformity::factory()->create(['pabrik' => 'ckp']);
    storedPhoto($mine, 'foto_temuan');
    storedPhoto($mine, 'foto_temuan', 'kedua.jpg');
    $otherPlant = NonConformity::factory()->create(['pabrik' => 'psr']);

    $this->actingAs($this->auditor)
        ->postJson('/non-conformities/bulk', ['action' => 'delete', 'ids' => [$mine->id, $otherPlant->id]])
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($mine->fresh())->toBeNull()
        ->and($otherPlant->fresh())->not->toBeNull();
    Storage::disk('uploads')->assertMissing(['foto_temuan/foto.jpg', 'foto_temuan/kedua.jpg']);
    expect(FindingPhoto::count())->toBe(0);
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

test('a finding can be edited, including its status, and new repair photos join the stored ones', function () {
    Smk3Criterion::create(['nomor' => 1, 'no_kriteria' => '6.5.3', 'kriteria' => 'Sertifikat peralatan', 'minor' => true, 'major' => false, 'kritis' => false]);
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $finding = NonConformity::factory()->open()->create(['pabrik' => 'ckp']);
    storedPhoto($finding, 'foto_perbaikan');

    $this->actingAs($this->auditor)->get("/non-conformities/{$finding->id}/edit")
        ->assertOk()
        ->assertSee(__('nc.edit.title', ['id' => $finding->id]))
        ->assertDontSee(__('nc.create.saveAndAnother'));

    $this->actingAs($this->auditor)
        ->put("/non-conformities/{$finding->id}", [
            'tanggal' => '2026-10-20', 'pabrik' => 'ckp', 'departemen' => 'OIL', 'deskripsi_temuan' => 'Sertifikat forklift habis',
            'no_klausul' => '6.5.3', 'kategori' => 'Minor', 'pic' => $pic->email, 'status' => '1',
            'foto_perbaikan' => [UploadedFile::fake()->image('perbaikan.jpg'), UploadedFile::fake()->image('perbaikan2.jpg')],
        ])
        ->assertRedirect(route('non-conformities'))
        ->assertSessionHas('success', __('nc.updated', ['id' => $finding->id]));

    $finding->refresh();
    expect($finding->deskripsi_temuan)->toBe('Sertifikat forklift habis')
        ->and($finding->isClosed())->toBeTrue()
        ->and($finding->photosOf('foto_perbaikan'))->toHaveCount(3);
    Storage::disk('uploads')->assertExists('foto_perbaikan/foto.jpg');
});

test('several photos can be added straight from the table', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);

    $this->actingAs($this->auditor)
        ->post("/non-conformities/{$finding->id}/photos/foto_temuan", ['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('message', __('nc.photosSaved', ['count' => 2, 'id' => $finding->id]));

    $photos = $finding->fresh()->photosOf('foto_temuan');
    expect($photos)->toHaveCount(2)
        ->and($photos[0]->path)->toStartWith('uploads/foto_temuan/')
        ->and($finding->fresh()->photosOf('foto_perbaikan'))->toBeEmpty();
    Storage::disk('uploads')->assertExists((string) str($photos[1]->path)->after('uploads/'));
});

test('a field holds at most the maximum number of photos', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
    foreach (range(1, FindingPhotos::MAX_PER_FIELD - 1) as $number) {
        storedPhoto($finding, 'foto_temuan', "{$number}.jpg");
    }

    $this->actingAs($this->auditor)
        ->post("/non-conformities/{$finding->id}/photos/foto_temuan", ['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photos' => __('nc.create.photoLimit', ['max' => FindingPhotos::MAX_PER_FIELD])]);

    // the other field has its own limit, and the last free place can still be used
    $this->actingAs($this->auditor)
        ->post("/non-conformities/{$finding->id}/photos/foto_perbaikan", ['photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]], ['Accept' => 'application/json'])
        ->assertOk();
    $this->actingAs($this->auditor)
        ->post("/non-conformities/{$finding->id}/photos/foto_temuan", ['photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
        ->assertOk();

    expect($finding->fresh()->photosOf('foto_temuan'))->toHaveCount(FindingPhotos::MAX_PER_FIELD);
});

test('adding photos needs at least one image', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
    $url = "/non-conformities/{$finding->id}/photos/foto_temuan";

    $this->actingAs($this->auditor)->post($url, [], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('photos');
    $this->actingAs($this->auditor)->post($url, ['photos' => [UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('photos.0');

    expect(FindingPhoto::count())->toBe(0);
});

test('auditees may add only repair photos to their own findings', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $own = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);
    $otherDepartment = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'QC']);
    $photo = fn () => ['photos' => [UploadedFile::fake()->image('foto.jpg')]];

    $this->actingAs($auditee)->post("/non-conformities/{$own->id}/photos/foto_perbaikan", $photo(), ['Accept' => 'application/json'])->assertOk();
    $this->actingAs($auditee)->post("/non-conformities/{$own->id}/photos/foto_temuan", $photo(), ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($auditee)->post("/non-conformities/{$otherDepartment->id}/photos/foto_perbaikan", $photo(), ['Accept' => 'application/json'])->assertNotFound();

    expect($own->fresh()->photosOf('foto_perbaikan'))->toHaveCount(1)
        ->and($own->fresh()->photosOf('foto_temuan'))->toBeEmpty();
});

test('one photo can be deleted, leaving the others', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
    $first = storedPhoto($finding, 'foto_temuan', 'a.jpg');
    $second = storedPhoto($finding, 'foto_temuan', 'b.jpg');

    $this->actingAs($this->auditor)->deleteJson("/non-conformities/{$finding->id}/photos/{$first->id}")
        ->assertOk()
        ->assertJsonPath('message', __('nc.photoDeleted', ['id' => $finding->id]));

    expect($first->fresh())->toBeNull()->and($second->fresh())->not->toBeNull();
    Storage::disk('uploads')->assertMissing('foto_temuan/a.jpg');
    Storage::disk('uploads')->assertExists('foto_temuan/b.jpg');
});

test('photos can only be deleted by those who may add them', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $own = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);
    $otherDepartment = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'QC']);
    $otherPlant = NonConformity::factory()->create(['pabrik' => 'psr']);

    $findingPhoto = storedPhoto($own, 'foto_temuan');
    $repairPhoto = storedPhoto($own, 'foto_perbaikan');
    $hidden = storedPhoto($otherDepartment, 'foto_perbaikan', 'lain.jpg');
    $foreign = storedPhoto($otherPlant, 'foto_temuan', 'psr.jpg');

    $this->actingAs($auditee)->deleteJson("/non-conformities/{$own->id}/photos/{$findingPhoto->id}")->assertForbidden();
    $this->actingAs($auditee)->deleteJson("/non-conformities/{$otherDepartment->id}/photos/{$hidden->id}")->assertNotFound();
    $this->actingAs($this->auditor)->deleteJson("/non-conformities/{$otherPlant->id}/photos/{$foreign->id}")->assertNotFound();
    // a visible finding in the URL does not open up another finding's photo
    $this->actingAs($auditee)->deleteJson("/non-conformities/{$own->id}/photos/{$hidden->id}")->assertNotFound();

    $this->actingAs($auditee)->deleteJson("/non-conformities/{$own->id}/photos/{$repairPhoto->id}")->assertOk();

    expect($repairPhoto->fresh())->toBeNull()
        ->and($findingPhoto->fresh())->not->toBeNull()
        ->and($hidden->fresh())->not->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

test('photos open in a modal on the same page, with the key facts of the finding', function () {
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp', 'deskripsi_temuan' => 'Panel listrik terbuka']);
    $photo = storedPhoto($finding, 'foto_temuan');
    storedPhoto($finding, 'foto_temuan', 'b.jpg');

    $response = $this->actingAs($this->auditor)->get('/non-conformities')
        ->assertOk()
        ->assertDontSee('<a href="'.asset('uploads/foto_temuan/foto.jpg').'"', false)
        ->assertSee('finding-photos-open', false)
        ->assertSee(__('nc.photoViewer.details'));

    preg_match('/data-photo-viewer="([^"]+)"/', $response->getContent(), $match);
    $viewer = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);

    expect($viewer['id'])->toBe($finding->id)
        ->and($viewer['photos']['foto_temuan'])->toHaveCount(2)
        ->and($viewer['photos']['foto_temuan'][0])->toBe(['id' => $photo->id, 'url' => asset('uploads/foto_temuan/foto.jpg')])
        ->and($viewer['photos']['foto_perbaikan'])->toBeEmpty()
        ->and($viewer['canDelete'])->toBe(['foto_temuan' => true, 'foto_perbaikan' => true])
        ->and(array_column($viewer['details'], 'value'))->toContain('Panel listrik terbuka');
});

test('the modal of an auditee only offers to delete repair photos', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp', 'departemen' => 'OIL']);
    storedPhoto($finding, 'foto_temuan');

    $response = $this->actingAs($auditee)->get('/non-conformities')->assertOk();

    preg_match('/data-photo-viewer="([^"]+)"/', $response->getContent(), $match);
    expect(json_decode(html_entity_decode($match[1], ENT_QUOTES), true)['canDelete'])
        ->toBe(['foto_temuan' => false, 'foto_perbaikan' => true]);
});

test('the migration moves stored photos to the new table and back', function () {
    $migration = require database_path('migrations/2026_11_04_000000_create_non_conformity_photos_table.php');
    $finding = NonConformity::factory()->create(['pabrik' => 'ckp']);
    $empty = NonConformity::factory()->create(['pabrik' => 'ckp']);
    $finding->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/foto_temuan/lama.jpg']);
    $finding->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/foto_temuan/baru.jpg']);
    $finding->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/foto_perbaikan/lama.jpg']);

    // down keeps the oldest photo of each field in the old columns
    $migration->down();
    $row = DB::table('non_conformities')->find($finding->id);
    expect($row->foto_temuan)->toBe('uploads/foto_temuan/lama.jpg')
        ->and($row->foto_perbaikan)->toBe('uploads/foto_perbaikan/lama.jpg')
        ->and(DB::table('non_conformities')->find($empty->id)->foto_temuan)->toBeNull();

    $migration->up();
    expect(Schema::hasColumn('non_conformities', 'foto_temuan'))->toBeFalse()
        ->and(FindingPhoto::orderBy('id')->pluck('path', 'field')->all())->toBe([
            'foto_temuan' => 'uploads/foto_temuan/lama.jpg',
            'foto_perbaikan' => 'uploads/foto_perbaikan/lama.jpg',
        ])
        ->and(FindingPhoto::where('non_conformity_id', $empty->id)->count())->toBe(0);
});
