<?php

namespace Database\Seeders;

use App\Models\NonConformity;
use Illuminate\Database\Seeder;

/**
 * Sample findings for trying out the Non-Conformities page locally.
 * Run with: php artisan db:seed --class=NonConformitySeeder
 */
class NonConformitySeeder extends Seeder
{
    public function run(): void
    {
        NonConformity::factory()->count(60)->create();
    }
}
