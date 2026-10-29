<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SMK3 audit criteria (legacy smk3_kriteria / smk3 tables). The clause number is
     * stored on its own instead of being prefixed to the text as in smk3_kriteria.
     */
    public function up(): void
    {
        Schema::create('smk3_kriteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('nomor')->index();
            // matches non_conformities.no_klausul
            $table->string('no_kriteria', 20)->unique();
            $table->text('kriteria');
            // which finding categories may be used with this criterion
            $table->boolean('minor')->default(false);
            $table->boolean('major')->default(false);
            $table->boolean('kritis')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smk3_kriteria');
    }
};
