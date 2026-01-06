<?php

use Illuminate\Database\Migrations\Migration;
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

        // Allow nomor_po to be optional (NULL). Keeping UNIQUE index is fine:
        // MySQL allows multiple NULL values in a UNIQUE index.
        DB::statement("ALTER TABLE `data_proyek` MODIFY `nomor_po` VARCHAR(255) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        // Revert to NOT NULL (may fail if existing rows contain NULL).
        DB::statement("ALTER TABLE `data_proyek` MODIFY `nomor_po` VARCHAR(255) NOT NULL");
    }
};
