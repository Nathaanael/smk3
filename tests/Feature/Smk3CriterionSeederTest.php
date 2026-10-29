<?php

use App\Models\Smk3Criterion;
use Database\Seeders\Smk3CriterionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('all 166 SMK3 criteria are seeded with separate clause numbers', function () {
    $this->seed(Smk3CriterionSeeder::class);

    expect(Smk3Criterion::count())->toBe(166);

    $first = Smk3Criterion::where('no_kriteria', '1.1.1')->sole();
    expect($first->kriteria)->toBe('Terdapat kebijakan K3 sesuai ketentuan')
        ->and($first->allowedCategories())->toBe(['Minor', 'Major']);

    // no criterion text still carries its number as a prefix
    expect(Smk3Criterion::pluck('kriteria')->filter(fn (string $text) => preg_match('/^\d+\.\d/', $text)))->toBeEmpty();
});

test('the seeder can run again without duplicating criteria', function () {
    $this->seed(Smk3CriterionSeeder::class);
    $this->seed(Smk3CriterionSeeder::class);

    expect(Smk3Criterion::count())->toBe(166);
});
