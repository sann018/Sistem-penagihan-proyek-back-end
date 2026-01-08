<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        // Untuk environment yang sudah terlanjur memiliki unique index pada `nomor_po`.
        // nomor_po memang boleh duplikat, jadi unique harus dihapus.
        $dbName = DB::selectOne('select database() as db')?->db;
        if (is_string($dbName) && $dbName !== '') {
            $hasUnique = DB::table('information_schema.statistics')
                ->where('table_schema', $dbName)
                ->where('table_name', 'data_proyek')
                ->where('index_name', 'data_proyek_nomor_po_unique')
                ->exists();

            if ($hasUnique) {
                DB::statement('ALTER TABLE `data_proyek` DROP INDEX `data_proyek_nomor_po_unique`');
            }

            // Pastikan ada index biasa untuk query.
            $hasIndex = DB::table('information_schema.statistics')
                ->where('table_schema', $dbName)
                ->where('table_name', 'data_proyek')
                ->where('index_name', 'data_proyek_nomor_po_index')
                ->exists();

            if (!$hasIndex) {
                DB::statement('ALTER TABLE `data_proyek` ADD INDEX `data_proyek_nomor_po_index` (`nomor_po`)');
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        $dbName = DB::selectOne('select database() as db')?->db;
        if (is_string($dbName) && $dbName !== '') {
            $hasIndex = DB::table('information_schema.statistics')
                ->where('table_schema', $dbName)
                ->where('table_name', 'data_proyek')
                ->where('index_name', 'data_proyek_nomor_po_index')
                ->exists();

            if ($hasIndex) {
                DB::statement('ALTER TABLE `data_proyek` DROP INDEX `data_proyek_nomor_po_index`');
            }
        }
    }
};
