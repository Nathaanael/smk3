<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** Write a legacy-style users dump and return its path relative to the project. */
function legacyUsersDump(): string
{
    $hash = Hash::make('lama12345');
    $sql = <<<SQL
        INSERT INTO `users` (`id`, `nama`, `email`, `departemen`, `password`, `otorisasi`, `created_at`, `updated_at`, `pabrik`) VALUES
        (11, 'Admin Auditor', 'adminauditor@indofood.co.id', 'HR', '{$hash}', 1, '2025-02-10 08:13:22', '2026-10-28 08:30:11', 'ckp'),
        (28, 'Fakhruddin Asy\\'ari', 'fakhruddin@icbp.indofood.co.id', 'WHRM', '{$hash}', 2, '2025-04-10 09:16:17', '2025-04-10 09:16:17', 'ckp'),
        (60, 'Pandu Maulana', 'pandu.maulana@icbp.indofood.co.id', 'hr', '{$hash}', 1, '2025-08-24 15:05:55', '2025-08-24 15:05:55', 'psr'),
        (76, 'Oscar, Putra', 'oscar@icbp.indofood.co.id', 'HR', '{$hash}', 1, '2025-08-28 01:49:43', '2026-02-10 02:25:47', 'ckp,ing');
        SQL;

    $path = 'storage/framework/testing/legacy-users.sql';
    @mkdir(dirname(base_path($path)), 0777, true);
    file_put_contents(base_path($path), $sql);

    return $path;
}

test('legacy auditors are imported with their old password', function () {
    User::factory()->admin()->create(['email' => 'adminauditor@indofood.co.id']);

    $this->artisan('smk3:import-legacy-users', ['file' => legacyUsersDump(), '--role' => 'auditor'])
        ->expectsOutputToContain('2 dari 3 akun Auditor diimpor')
        ->assertSuccessful();

    $pandu = User::where('email', 'pandu.maulana@icbp.indofood.co.id')->sole();
    $oscar = User::where('email', 'oscar@icbp.indofood.co.id')->sole();

    expect($pandu->otorisasi)->toBe(Role::Auditor)
        ->and($pandu->departemen)->toBe('HR')
        ->and(Hash::check('lama12345', $pandu->password))->toBeTrue()
        ->and($oscar->name)->toBe('Oscar, Putra')
        ->and($oscar->pabrik)->toBe('ckp,ing')
        // the existing admin keeps its role; auditees are not part of this run
        ->and(User::where('email', 'adminauditor@indofood.co.id')->value('otorisasi'))->toBe(Role::Admin)
        ->and(User::where('otorisasi', Role::Auditee)->exists())->toBeFalse();
});

test('running the import again changes nothing', function () {
    $file = legacyUsersDump();

    $this->artisan('smk3:import-legacy-users', ['file' => $file])->assertSuccessful();
    $this->artisan('smk3:import-legacy-users', ['file' => $file])
        ->expectsOutputToContain('0 dari 3 akun Auditor diimpor')
        ->assertSuccessful();

    expect(User::where('otorisasi', Role::Auditor)->count())->toBe(3);
});

test('a dry run saves nothing', function () {
    $this->artisan('smk3:import-legacy-users', ['file' => legacyUsersDump(), '--dry-run' => true])->assertSuccessful();

    expect(User::count())->toBe(0);
});
