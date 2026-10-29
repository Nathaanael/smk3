<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copies user accounts of one role from the legacy `users` dump (smk3old).
 * Password hashes are kept, so users sign in with their old password. Accounts whose
 * email already exists are left untouched, so the command can be run again safely.
 */
class ImportLegacyUsers extends Command
{
    protected $signature = 'smk3:import-legacy-users
        {file=db/users.sql : phpMyAdmin dump of the legacy users table}
        {--role=auditor : auditor, auditee or admin}
        {--dry-run : Only show what would be imported}';

    protected $description = 'Import user accounts of one role from the legacy SMK3 database dump';

    public function handle(): int
    {
        $role = Role::fromName((string) $this->option('role'));
        $path = base_path((string) $this->argument('file'));
        $dryRun = (bool) $this->option('dry-run');

        $legacy = array_values(array_filter(
            LegacySqlDump::rows($path, 'users'),
            fn (array $row) => (int) $row['otorisasi'] === $role->value,
        ));

        $existing = User::all()->keyBy(fn (User $user) => mb_strtolower($user->email));
        $report = [];
        $imported = 0;

        foreach ($legacy as $row) {
            $email = trim((string) $row['email']);
            $pabrik = mb_strtolower(str_replace(' ', '', (string) $row['pabrik']));
            $departemen = $this->department((string) $row['departemen'], $pabrik);
            $user = $existing->get(mb_strtolower($email));

            $result = match (true) {
                $user && $user->otorisasi !== $role => "dilewati: sudah terdaftar sebagai {$user->otorisasi->name}",
                (bool) $user => 'dilewati: sudah ada',
                ! str_starts_with((string) $row['password'], '$2y$') => 'dilewati: format password tidak dikenal',
                default => null,
            };

            if ($result === null) {
                $notes = array_filter([
                    isset(User::plantOptions()[$pabrik]) ? null : "pabrik \"{$pabrik}\" tidak dikenal",
                    $departemen === null ? "departemen \"{$row['departemen']}\" tidak dikenal" : null,
                ]);

                if (! $dryRun) {
                    DB::table('users')->insert([
                        'name' => trim((string) $row['nama']),
                        'email' => $email,
                        'password' => $row['password'],
                        'departemen' => $departemen ?? $row['departemen'],
                        'otorisasi' => $role->value,
                        'pabrik' => $pabrik,
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                    ]);
                }

                $imported++;
                $result = ($dryRun ? 'akan diimpor' : 'diimpor').($notes ? ' ('.implode('; ', $notes).')' : '');
            }

            $report[] = [trim((string) $row['nama']), $email, $pabrik, $departemen ?? $row['departemen'], $result];
        }

        $this->table(['Nama', 'Email', 'Pabrik', 'Departemen', 'Hasil'], $report);
        $this->info(sprintf(
            '%d dari %d akun %s %s.',
            $imported,
            count($legacy),
            $role->name,
            $dryRun ? 'akan diimpor (dry run, belum ada yang disimpan)' : 'diimpor',
        ));

        return self::SUCCESS;
    }

    /** Canonical department code (legacy rows mix upper and lower case); null when unknown. */
    private function department(string $value, string $pabrik): ?string
    {
        foreach (array_keys(User::departmentsForPlant($pabrik)) as $code) {
            if (strcasecmp($code, trim($value)) === 0) {
                return $code;
            }
        }

        return null;
    }
}
