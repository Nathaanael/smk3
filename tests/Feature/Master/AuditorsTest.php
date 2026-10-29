<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function auditorAccount(array $attributes = []): User
{
    return User::factory()->create($attributes + ['otorisasi' => Role::Auditor, 'pabrik' => 'ckp', 'departemen' => 'QC']);
}

test('admins see every auditor and no other roles', function () {
    auditorAccount(['name' => 'Andi Cikampek']);
    auditorAccount(['name' => 'Budi Pasuruan', 'pabrik' => 'psr']);
    User::factory()->create(['name' => 'Citra Auditee', 'pabrik' => 'ckp']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/master/auditor')
        ->assertOk()
        ->assertSeeInOrder(['Andi Cikampek', 'Budi Pasuruan'])
        ->assertDontSee('Citra Auditee');
});

test('auditors only see auditors of their own plants', function () {
    $me = auditorAccount(['name' => 'Saya', 'pabrik' => 'ckp,ing']);
    auditorAccount(['name' => 'Rekan ING', 'pabrik' => 'ing', 'departemen' => 'premix']);
    auditorAccount(['name' => 'Orang Pasuruan', 'pabrik' => 'psr']);

    $this->actingAs($me)
        ->get('/master/auditor')
        ->assertOk()
        ->assertSee('Rekan ING')
        ->assertDontSee('Orang Pasuruan');
});

test('auditees cannot open the auditor master', function () {
    $this->actingAs(User::factory()->create())->get('/master/auditor')->assertForbidden();
});

test('an auditor can be added manually', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditor', [
            'name' => 'Dewi', 'email' => 'dewi@indofood.co.id', 'pabrik' => 'ing', 'departemen' => 'spices',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])
        ->assertRedirect('/master/auditor')
        ->assertSessionHas('success');

    $user = User::where('email', 'dewi@indofood.co.id')->sole();
    expect($user->otorisasi)->toBe(Role::Auditor)
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

test('the department must belong to the chosen plant', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditor', [
            'name' => 'Dewi', 'email' => 'dewi@indofood.co.id', 'pabrik' => 'ing', 'departemen' => 'OIL',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])
        ->assertSessionHasErrorsIn('account', 'departemen');
});

test('auditors cannot give an auditor a plant outside their own', function () {
    $this->actingAs(auditorAccount())
        ->post('/master/auditor', [
            'name' => 'Eko', 'email' => 'eko@indofood.co.id', 'pabrik' => 'psr', 'departemen' => 'QC',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])
        ->assertSessionHasErrorsIn('account', 'pabrik');
});

test('editing without a password keeps the old one', function () {
    $auditor = auditorAccount(['password' => 'lama12345']);

    $this->actingAs(User::factory()->admin()->create())
        ->put("/master/auditor/{$auditor->id}", ['name' => 'Nama Baru', 'email' => $auditor->email, 'pabrik' => 'ckp,ing', 'departemen' => 'premix'])
        ->assertRedirect('/master/auditor');

    $auditor->refresh();
    expect($auditor->name)->toBe('Nama Baru')
        ->and($auditor->pabrik)->toBe('ckp,ing')
        ->and(Hash::check('lama12345', $auditor->password))->toBeTrue();
});

test('auditees cannot be edited or deleted through the auditor master', function () {
    $auditee = User::factory()->create(['pabrik' => 'ckp']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete("/master/auditor/{$auditee->id}")->assertNotFound();
    $this->actingAs($admin)->put("/master/auditor/{$auditee->id}", ['name' => 'X'])->assertNotFound();
});

test('an auditor cannot delete their own account', function () {
    $me = auditorAccount();

    $this->actingAs($me)->delete("/master/auditor/{$me->id}")->assertSessionHas('error');
    expect($me->fresh())->not->toBeNull();
});

test('another auditor can be deleted', function () {
    $other = auditorAccount();

    $this->actingAs(auditorAccount())->delete("/master/auditor/{$other->id}")->assertSessionHas('success');
    expect($other->fresh())->toBeNull();
});

test('the template lists the current auditors and can be uploaded back', function () {
    auditorAccount(['name' => 'Andi', 'email' => 'andi@indofood.co.id', 'password' => 'lama12345']);

    $response = $this->actingAs(User::factory()->admin()->create())->get('/master/auditor/template');
    $response->assertOk()->assertDownload('template-auditor.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $workbook = IOFactory::load($path);
    $rows = $workbook->getSheet(0)->toArray(null, true, false, false);

    expect($rows[0])->toBe(ACCOUNT_HEADERS)
        ->and($rows[1])->toEqual(['Andi', 'andi@indofood.co.id', null, 'QC', 'ckp'])
        ->and($workbook->getSheetByName('Referensi'))->not->toBeNull();

    // move Andi to ING and add a new auditor (department given by name, plant as "CKP + ING")
    $rows[1][3] = 'premix';
    $rows[1][4] = 'ing';
    $rows[] = ['Fajar', 'fajar@indofood.co.id', '12345678', 'Quality Control', 'CKP + ING'];

    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditor/import', ['file' => accountWorkbook($rows)])
        ->assertRedirect('/master/auditor')
        ->assertSessionHas('success', __('account.import.done', ['created' => 1, 'updated' => 1, 'unchanged' => 0]));

    $andi = User::where('email', 'andi@indofood.co.id')->sole();
    $fajar = User::where('email', 'fajar@indofood.co.id')->sole();
    expect($andi->pabrik)->toBe('ing')
        ->and(Hash::check('lama12345', $andi->password))->toBeTrue()
        ->and($fajar->otorisasi)->toBe(Role::Auditor)
        ->and($fajar->pabrik)->toBe('ckp,ing')
        ->and($fajar->departemen)->toBe('QC');
});

test('an upload with problems saves nothing and lists every problem', function () {
    User::factory()->create(['email' => 'auditee@indofood.co.id']);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditor/import', ['file' => accountWorkbook([
            ACCOUNT_HEADERS,
            ['Baru', 'baru@indofood.co.id', '12345678', 'QC', 'ckp'],
            ['Tanpa Password', 'kosong@indofood.co.id', '', 'QC', 'ckp'],
            ['Salah Email', 'bukan-email', '12345678', 'QC', 'ckp'],
            ['Auditee', 'auditee@indofood.co.id', '12345678', 'QC', 'ckp'],
            ['Dept Salah', 'dept@indofood.co.id', '12345678', 'OIL', 'ing'],
            ['Dobel', 'baru@indofood.co.id', '12345678', 'QC', 'ckp'],
        ])])
        ->assertSessionHas('importErrors', fn (array $errors) => count($errors) === 5);

    expect(User::where('email', 'baru@indofood.co.id')->exists())->toBeFalse();
});

test('auditors cannot upload accounts for other plants', function () {
    $this->actingAs(auditorAccount())
        ->post('/master/auditor/import', ['file' => accountWorkbook([
            ACCOUNT_HEADERS,
            ['Orang PSR', 'psr@indofood.co.id', '12345678', 'QC', 'psr'],
        ])])
        ->assertSessionHas('importErrors', [__('account.import.badPlant', ['row' => 2, 'value' => 'psr'])]);
});
