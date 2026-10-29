<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('every role can open the SMK3 pages', function (Role $role, string $path, string $title) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->get($path)
        ->assertOk()
        ->assertSee(__($title));
})->with([Role::Admin, Role::Auditor, Role::Auditee])->with([
    ['/progress-monitoring', 'smk3.progressMonitoring'],
    ['/non-conformities', 'smk3.nonConformities'],
]);

test('the sidebar links to both SMK3 pages', function () {
    $this->actingAs(User::factory()->create())
        ->get('/progress-monitoring')
        ->assertSee('href="/progress-monitoring"', false)
        ->assertSee('href="/non-conformities"', false);
});

test('non-admins are sent from the home page to progress monitoring', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('progress-monitoring'));
});

test('admins and auditors see the master menu', function (Role $role) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->get('/progress-monitoring')
        ->assertSee(__('master.title'))
        ->assertSee('href="/master/auditee"', false)
        ->assertSee('href="/master/auditor"', false)
        ->assertSee('href="/master/clauses"', false);
})->with([Role::Admin, Role::Auditor]);

test('auditees do not see the master menu', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditee]))
        ->get('/progress-monitoring')
        ->assertDontSee('href="/master/auditee"', false);
});
