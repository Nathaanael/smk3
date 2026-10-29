<?php

namespace App\Console\Commands;

use App\Models\NonConformity;
use App\Models\User;
use App\Services\ImageCompressor;
use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Copies the findings (`list` table) of the legacy SMK3 system, keeping their ids.
 * Photos are copied from the legacy public folder into public/uploads and compressed
 * on the way. Findings whose id already exists are skipped, so it can be run again.
 */
class ImportLegacyFindings extends Command
{
    protected $signature = 'smk3:import-legacy-findings
        {file=db/list.sql : phpMyAdmin dump of the legacy list table}
        {--photos=smk3old/public : Legacy public folder that holds uploads/foto_temuan and uploads/foto_perbaikan}
        {--dry-run : Only show what would be imported}';

    protected $description = 'Import findings (non-conformities) from the legacy SMK3 database dump';

    public function handle(ImageCompressor $images): int
    {
        $rows = LegacySqlDump::rows(base_path((string) $this->argument('file')), 'list');
        $photoRoot = base_path((string) $this->option('photos'));
        $dryRun = (bool) $this->option('dry-run');

        $existing = NonConformity::pluck('id')->flip();
        $users = User::pluck('email')->mapWithKeys(fn (string $email) => [mb_strtolower($email) => true]);
        $stats = ['imported' => 0, 'skipped' => 0];
        $warnings = [];

        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            if (isset($existing[$row['id']])) {
                $stats['skipped']++;

                continue;
            }

            $pic = trim((string) $row['pic']);
            if (! isset($users[mb_strtolower($pic)])) {
                $warnings[] = "#{$row['id']}: PIC {$pic} tidak terdaftar sebagai akun (disimpan apa adanya)";
            }

            $photos = [];
            foreach (['foto_temuan', 'foto_perbaikan'] as $column) {
                $photos[$column] = $this->photo($row[$column], $column, $photoRoot, $images, $dryRun, $warnings, $row['id']);
            }

            if (! $dryRun) {
                DB::table('non_conformities')->insert([
                    'id' => $row['id'],
                    'tanggal' => $row['tanggal'],
                    'pabrik' => mb_strtolower(trim((string) $row['pabrik'])),
                    'departemen' => trim((string) $row['departemen']),
                    'deskripsi_temuan' => trim((string) $row['deskripsi_temuan']),
                    'no_klausul' => trim((string) $row['no_klausul']),
                    'kategori' => trim((string) $row['kategori']),
                    'pic' => $pic,
                    'status' => (bool) (int) $row['status'],
                    // the legacy table has no timestamps; the finding date is the closest we have
                    'created_at' => $row['tanggal'],
                    'updated_at' => $row['tanggal'],
                ] + $photos);
            }

            $stats['imported']++;
        }

        $bar->finish();
        $this->newLine(2);

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        $this->info(sprintf(
            '%d temuan %s, %d sudah ada (dilewati).',
            $stats['imported'],
            $dryRun ? 'akan diimpor (dry run, belum ada yang disimpan)' : 'diimpor',
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * Copy (and compress) one legacy photo; returns the new "uploads/..." path, or null when missing.
     *
     * @param  list<string>  $warnings
     */
    private function photo(?string $path, string $directory, string $root, ImageCompressor $images, bool $dryRun, array &$warnings, int $id): ?string
    {
        if (! $path) {
            return null;
        }

        $source = $root.'/'.ltrim($path, '/');
        if (! is_file($source)) {
            $warnings[] = "#{$id}: file {$path} tidak ditemukan (kolom {$directory} dikosongkan)";

            return null;
        }

        if ($dryRun) {
            return $path;
        }

        $stored = 'uploads/'.$images->store(new UploadedFile($source, basename($source), null, null, true), $directory);
        // the report thumbnail too, so the first export with photos is not slow
        $images->thumbnail($stored);

        return $stored;
    }
}
