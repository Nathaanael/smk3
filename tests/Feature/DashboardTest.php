<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
