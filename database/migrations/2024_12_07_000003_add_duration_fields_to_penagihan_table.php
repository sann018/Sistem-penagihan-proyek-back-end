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
            // Tambah field untuk countdown timer
            $table->integer('estimasi_durasi_hari')->default(7)->comment('Estimasi durasi pengerjaan proyek dalam hari');
            $table->date('tanggal_mulai')->nullable()->comment('Tanggal mulai countdown timer proyek');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penagihan', function (Blueprint $table) {
            $table->dropColumn('estimasi_durasi_hari');
            $table->dropColumn('tanggal_mulai');
        });
    }
};
