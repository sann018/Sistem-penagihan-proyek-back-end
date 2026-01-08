<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: altering ENUM requires re-stating all allowed values.
        DB::statement("ALTER TABLE `notifikasi` MODIFY `jenis_notifikasi` ENUM('jatuh_tempo','h_minus_7','h_minus_5','h_minus_3','h_minus_1','lunas','revisi_mitra','status_berubah','proyek_baru','prioritas_berubah','reminder','info','warning','error') NOT NULL DEFAULT 'info'");
    }

    public function down(): void
    {
        // Revert to the original enum definition (without h_minus_5).
        DB::statement("ALTER TABLE `notifikasi` MODIFY `jenis_notifikasi` ENUM('jatuh_tempo','h_minus_7','h_minus_3','h_minus_1','lunas','revisi_mitra','status_berubah','proyek_baru','prioritas_berubah','reminder','info','warning','error') NOT NULL DEFAULT 'info'");
    }
};
