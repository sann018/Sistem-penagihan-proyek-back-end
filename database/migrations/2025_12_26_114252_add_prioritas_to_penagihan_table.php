<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penagihan', function (Blueprint $table) {
            // Kolom prioritas: null = tidak diprioritaskan, 1 = prioritas tinggi, 2 = prioritas auto (mendekati deadline)
            $table->integer('prioritas')->nullable()->after('status_procurement');
            $table->timestamp('prioritas_updated_at')->nullable()->after('prioritas');
            
            // Index untuk performance query dashboard
            $table->index('prioritas');
            $table->index(['prioritas', 'tanggal_mulai', 'estimasi_durasi_hari']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penagihan', function (Blueprint $table) {
            $table->dropIndex(['penagihan_prioritas_index']);
            $table->dropIndex(['penagihan_prioritas_tanggal_mulai_estimasi_durasi_hari_index']);
            $table->dropColumn(['prioritas', 'prioritas_updated_at']);
        });
    }
};
