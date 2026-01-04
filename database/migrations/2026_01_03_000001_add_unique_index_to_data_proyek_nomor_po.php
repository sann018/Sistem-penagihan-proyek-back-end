<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        // Pre-check: jika sudah ada duplikat nomor_po, unique index akan gagal.
        // Berikan pesan yang jelas agar bisa dibersihkan dulu.
        $duplicates = DB::table('data_proyek')
            ->select('nomor_po', DB::raw('COUNT(*) as total'))
            ->whereNotNull('nomor_po')
            ->groupBy('nomor_po')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $examples = $duplicates
                ->map(fn ($row) => (string) $row->nomor_po . ' (' . (int) $row->total . ')')
                ->implode(', ');

            throw new \RuntimeException(
                'Tidak bisa menambahkan UNIQUE untuk nomor_po karena masih ada duplikat. ' .
                'Contoh duplikat: ' . $examples . '. ' .
                'Silakan bersihkan duplikat terlebih dahulu, lalu jalankan migrasi lagi.'
            );
        }

        Schema::table('data_proyek', function (Blueprint $table) {
            // Pastikan tidak ada index dengan nama sama (Laravel akan skip jika sudah ada dalam beberapa DB,
            // tapi untuk konsisten kita pakai nama index eksplisit).
            $table->unique('nomor_po', 'data_proyek_nomor_po_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        Schema::table('data_proyek', function (Blueprint $table) {
            $table->dropUnique('data_proyek_nomor_po_unique');
        });
    }
};
