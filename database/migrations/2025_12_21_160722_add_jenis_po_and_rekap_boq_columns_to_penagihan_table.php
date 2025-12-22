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
            // Cek jika kolom belum ada baru tambahkan
            if (!Schema::hasColumn('penagihan', 'jenis_po')) {
                $table->string('jenis_po')->nullable()->after('pid');
            }
            
            if (!Schema::hasColumn('penagihan', 'rekap_boq')) {
                $table->string('rekap_boq')->default('Belum Rekap')->after('status_procurement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penagihan', function (Blueprint $table) {
            $table->dropColumn(['jenis_po', 'rekap_boq']);
        });
    }
};
