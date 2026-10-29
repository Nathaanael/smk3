<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auditeeAccount(array $attributes = []): User
{
    return User::factory()->create($attributes + ['otorisasi' => Role::Auditee, 'pabrik' => 'ckp', 'departemen' => 'OIL']);
}

test('the auditee master lists auditees only', function () {
    auditeeAccount(['name' => 'Andi Auditee']);
    User::factory()->create(['name' => 'Budi Auditor', 'otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/master/auditee')
        ->assertOk()
        ->assertSee('Andi Auditee')
        ->assertDontSee('Budi Auditor');
});

test('auditors only see auditees of their own plants', function () {
    auditeeAccount(['name' => 'Orang CKP']);
    auditeeAccount(['name' => 'Orang PSR', 'pabrik' => 'psr']);

    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']))
        ->get('/master/auditee')
        ->assertOk()
        ->assertSee('Orang CKP')
        ->assertDontSee('Orang PSR');
});

test('an auditee can be added manually', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditee', [
            'name' => 'Dewi', 'email' => 'dewi@indofood.co.id', 'pabrik' => 'ckp', 'departemen' => 'WHFG',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])
        ->assertRedirect('/master/auditee');

    expect(User::where('email', 'dewi@indofood.co.id')->value('otorisasi'))->toBe(Role::Auditee);
});

test('changing an auditee email carries their findings along', function () {
    $auditee = auditeeAccount(['email' => 'lama@indofood.co.id']);
    $finding = NonConformity::factory()->create(['pic' => 'lama@indofood.co.id']);

    $this->actingAs(User::factory()->admin()->create())
        ->put("/master/auditee/{$auditee->id}", ['name' => $auditee->name, 'email' => 'baru@indofood.co.id', 'pabrik' => 'ckp', 'departemen' => 'OIL'])
        ->assertRedirect('/master/auditee');

    expect($finding->fresh()->pic)->toBe('baru@indofood.co.id');
});

test('an auditee who is the PIC of findings cannot be deleted', function () {
    $auditee = auditeeAccount();
    NonConformity::factory()->create(['pic' => $auditee->email]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete("/master/auditee/{$auditee->id}")
        ->assertSessionHas('error', __('account.inUse', ['name' => $auditee->name, 'count' => 1]));

    expect($auditee->fresh())->not->toBeNull();
});

test('an auditee without findings can be deleted', function () {
    $auditee = auditeeAccount();

    $this->actingAs(User::factory()->admin()->create())->delete("/master/auditee/{$auditee->id}")->assertSessionHas('success');
    expect($auditee->fresh())->toBeNull();
});

test('auditors cannot be changed through the auditee master', function () {
    $auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => 'ckp']);

    $this->actingAs(User::factory()->admin()->create())->delete("/master/auditee/{$auditor->id}")->assertNotFound();
});

test('the auditee template can be uploaded back', function () {
    auditeeAccount(['email' => 'andi@indofood.co.id']);
    User::factory()->create(['email' => 'auditor@indofood.co.id', 'otorisasi' => Role::Auditor]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/master/auditee/template')
        ->assertOk()
        ->assertDownload('template-auditee.xlsx');

    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditee/import', ['file' => accountWorkbook([
            ACCOUNT_HEADERS,
            ['Andi', 'andi@indofood.co.id', '', 'BMB', 'ckp'],
            ['Citra', 'citra@indofood.co.id', '12345678', 'premix', 'ing'],
        ])])
        ->assertSessionHas('success', __('account.import.done', ['created' => 1, 'updated' => 1, 'unchanged' => 0]));

    expect(User::where('email', 'citra@indofood.co.id')->value('otorisasi'))->toBe(Role::Auditee);

    // an auditor's email cannot be uploaded as an auditee
    $this->actingAs(User::factory()->admin()->create())
        ->post('/master/auditee/import', ['file' => accountWorkbook([
            ACCOUNT_HEADERS,
            ['Auditor', 'auditor@indofood.co.id', '12345678', 'QC', 'ckp'],
        ])])
        ->assertSessionHas('importErrors', [__('account.import.otherRole', ['row' => 2, 'email' => 'auditor@indofood.co.id', 'role' => 'Auditor'])]);
});
