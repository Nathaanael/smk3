<?php

namespace Database\Seeders;

use App\Models\Smk3Criterion;
use Illuminate\Database\Seeder;

/**
 * Loads the SMK3 criteria from database/data/smk3_kriteria.php. Safe to run again:
 * rows are matched on no_kriteria and updated in place.
 */
class Smk3CriterionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = array_map(
            fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
            require database_path('data/smk3_kriteria.php'),
        );

        Smk3Criterion::upsert($rows, ['no_kriteria'], ['nomor', 'kriteria', 'minor', 'major', 'kritis', 'updated_at']);
    }
}
