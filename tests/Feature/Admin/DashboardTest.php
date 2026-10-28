<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins are sent from the home page to the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/')
        ->assertRedirect(route('admin.dashboard'));
});

test('admins can view the dashboard greeting', function () {
    $admin = User::factory()->admin()->create(['name' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee(__('dashboard.welcome'));
});

test('non-admins cannot access the admin dashboard', function (Role $role) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->get('/admin/dashboard')
        ->assertForbidden();
})->with([Role::Auditor, Role::Auditee]);

test('template demo pages are no longer reachable', function (string $path) {
    $this->actingAs(User::factory()->admin()->create())
        ->get($path)
        ->assertNotFound();
})->with(['/ecommerce', '/calendar', '/profile', '/basic-tables', '/buttons']);
