<?php

use App\Models\NonConformity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Legacy-style dump + photo folder under storage/framework/testing; returns [dump, photo root] relative to the project. */
function legacyFindingsFixture(): array
{
    $root = 'storage/framework/testing/legacy-findings';
    @mkdir(base_path("{$root}/public/uploads/foto_temuan"), 0777, true);

    $photo = imagecreatetruecolor(2400, 1800);
    imagejpeg($photo, base_path("{$root}/public/uploads/foto_temuan/kabel.jpg"), 100);

    $sql = <<<'SQL'
        INSERT INTO `list` (`id`, `tanggal`, `departemen`, `foto_temuan`, `no_klausul`, `kategori`, `klausul_hygiene`, `pic`, `foto_perbaikan`, `status`, `deskripsi_temuan`, `pabrik`) VALUES
        (680, '2025-08-27', 'OIL', 'uploads/foto_temuan/kabel.jpg', '7.1.1', 'Minor', NULL, 'helmi@icbp.indofood.co.id', NULL, b'1', 'Kabel terkelupas, \'segera\' diganti', 'ckp'),
        (681, '2025-08-20', 'HR', 'uploads/foto_temuan/hilang.jpg', '6.4.3', 'Major', NULL, 'sodri@icbp.indofood.co.id', NULL, b'0', 'Saluran air berlubang', 'psr');
        SQL;
    file_put_contents(base_path("{$root}/list.sql"), $sql);

    return ["{$root}/list.sql", "{$root}/public"];
}

test('legacy findings are imported with their ids and compressed photos', function () {
    Storage::fake('uploads');
    [$dump, $photos] = legacyFindingsFixture();

    $this->artisan('smk3:import-legacy-findings', ['file' => $dump, '--photos' => $photos])
        ->expectsOutputToContain('2 temuan diimpor')
        ->expectsOutputToContain('hilang.jpg tidak ditemukan')
        ->assertSuccessful();

    $closed = NonConformity::findOrFail(680);
    $open = NonConformity::findOrFail(681);

    expect($closed->status)->toBeTrue()
        ->and($closed->deskripsi_temuan)->toBe("Kabel terkelupas, 'segera' diganti")
        ->and($closed->foto_temuan)->toStartWith('uploads/foto_temuan/')->toEndWith('.jpg')
        ->and($open->status)->toBeFalse()
        ->and($open->foto_temuan)->toBeNull();

    [$width] = getimagesizefromstring(Storage::disk('uploads')->get(str($closed->foto_temuan)->after('uploads/')));
    expect($width)->toBe(1600);
});

test('running the findings import again changes nothing', function () {
    Storage::fake('uploads');
    [$dump, $photos] = legacyFindingsFixture();

    $this->artisan('smk3:import-legacy-findings', ['file' => $dump, '--photos' => $photos])->assertSuccessful();
    $this->artisan('smk3:import-legacy-findings', ['file' => $dump, '--photos' => $photos])
        ->expectsOutputToContain('0 temuan diimpor, 2 sudah ada')
        ->assertSuccessful();

    expect(NonConformity::count())->toBe(2);
});
