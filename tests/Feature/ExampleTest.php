<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->actingAs(User::factory()->create())->get('/ecommerce');

    $response->assertStatus(200);
});
