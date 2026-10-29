<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keywords per criterion ("tikus, hama, kecoa") used to suggest clauses for a finding
     * whose words do not appear in the criterion text itself.
     */
    public function up(): void
    {
        Schema::table('smk3_kriteria', function (Blueprint $table) {
            $table->text('kata_kunci')->nullable()->after('kriteria');
        });
    }

    public function down(): void
    {
        Schema::table('smk3_kriteria', function (Blueprint $table) {
            $table->dropColumn('kata_kunci');
        });
    }
};
