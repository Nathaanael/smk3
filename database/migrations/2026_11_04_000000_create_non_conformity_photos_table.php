<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A finding may now hold several finding / repair photos: they move from the single
 * foto_temuan / foto_perbaikan columns to their own table. Files on disk stay where they are.
 */
return new class extends Migration
{
    private const FIELDS = ['foto_temuan', 'foto_perbaikan'];

    public function up(): void
    {
        Schema::create('non_conformity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('non_conformity_id')->constrained('non_conformities')->cascadeOnDelete();
            // foto_temuan or foto_perbaikan
            $table->string('field', 20);
            // "uploads/<folder>/<file>", relative to public/
            $table->string('path');
            $table->timestamps();

            $table->index(['non_conformity_id', 'field']);
        });

        DB::table('non_conformities')
            ->select(['id', 'created_at', 'updated_at', ...self::FIELDS])
            ->where(fn ($query) => $query->whereNotNull('foto_temuan')->orWhereNotNull('foto_perbaikan'))
            ->chunkById(500, function ($findings) {
                $photos = [];
                foreach ($findings as $finding) {
                    foreach (self::FIELDS as $field) {
                        if ($finding->{$field}) {
                            $photos[] = [
                                'non_conformity_id' => $finding->id,
                                'field' => $field,
                                'path' => $finding->{$field},
                                'created_at' => $finding->created_at,
                                'updated_at' => $finding->updated_at,
                            ];
                        }
                    }
                }
                DB::table('non_conformity_photos')->insert($photos);
            });

        Schema::table('non_conformities', function (Blueprint $table) {
            $table->dropColumn(self::FIELDS);
        });
    }

    /** Back to one photo per field: only the oldest of each is kept in the columns. */
    public function down(): void
    {
        Schema::table('non_conformities', function (Blueprint $table) {
            $table->string('foto_temuan')->nullable();
            $table->string('foto_perbaikan')->nullable();
        });

        // newest first, so the oldest photo of each field is written last
        DB::table('non_conformity_photos')->chunkByIdDesc(500, function ($photos) {
            foreach ($photos as $photo) {
                DB::table('non_conformities')->where('id', $photo->non_conformity_id)->update([$photo->field => $photo->path]);
            }
        });

        Schema::dropIfExists('non_conformity_photos');
    }
};
