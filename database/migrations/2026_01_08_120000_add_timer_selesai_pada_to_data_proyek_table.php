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
        Schema::table('data_proyek', function (Blueprint $table) {
            // Flag manual untuk "Tandai Selesai" (timer tetap berjalan)
            $table->timestamp('timer_selesai_pada')->nullable()->after('tanggal_mulai');
            $table->index('timer_selesai_pada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_proyek', function (Blueprint $table) {
            $table->dropIndex(['timer_selesai_pada']);
            $table->dropColumn('timer_selesai_pada');
        });
    }
};
