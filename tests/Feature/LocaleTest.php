<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the interface defaults to Indonesian', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('<html lang="id"', false)
        ->assertSee('Selamat datang di SMK3 Monitoring.');
});

test('users can switch the interface to English', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from('/dashboard')
        ->post('/locale', ['locale' => 'en'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('locale', 'en');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertSee('<html lang="en"', false)
        ->assertSee('Welcome to SMK3 Monitoring.');
});

test('guests can switch the language on the login page', function () {
    $this->from('/login')->post('/locale', ['locale' => 'en'])->assertRedirect('/login');

    $this->get('/login')->assertSee('Sign in with your email and password');
});

test('unsupported languages are rejected', function () {
    $this->post('/locale', ['locale' => 'ar'])->assertSessionHasErrors('locale');

    $this->assertNull(session('locale'));
});

test('every Indonesian string has an English counterpart', function () {
    $en = json_decode(file_get_contents(lang_path('en.json')), true);
    $id = json_decode(file_get_contents(lang_path('id.json')), true);

    expect(array_diff_key($id, $en))->toBeEmpty();
});
