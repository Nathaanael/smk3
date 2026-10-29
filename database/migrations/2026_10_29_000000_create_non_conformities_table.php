<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit findings (the legacy `list` table). Column names follow the legacy
     * schema so existing data can be imported without remapping.
     */
    public function up(): void
    {
        Schema::create('non_conformities', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('pabrik', 10)->index();
            $table->string('departemen', 50)->index();
            $table->text('deskripsi_temuan');
            $table->string('foto_temuan')->nullable();
            $table->string('no_klausul', 50)->nullable();
            $table->string('kategori', 20)->nullable();
            $table->string('pic')->nullable();
            $table->string('foto_perbaikan')->nullable();
            // false = Open, true = Closed
            $table->boolean('status')->default(false);
            $table->timestamps();

            $table->index(['pabrik', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_conformities');
    }
};
