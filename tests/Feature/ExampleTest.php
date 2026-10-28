<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard');

    $response->assertStatus(200);
});
