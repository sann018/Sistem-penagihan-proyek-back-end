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
        if (!Schema::hasTable('data_proyek')) {
            return;
        }

        Schema::table('data_proyek', function (Blueprint $table) {
            // nomor_po boleh duplikat, jadi cukup INDEX biasa untuk performa query.
            // Guard: jika index sudah ada, jangan coba buat lagi.
            $schema = Schema::getConnection()->getSchemaBuilder();
            $indexes = $schema->getIndexes('data_proyek');

            $hasIndex = false;
            foreach ($indexes as $index) {
                $name = $index['name'] ?? null;
                if (is_string($name) && strtolower($name) === 'data_proyek_nomor_po_index') {
                    $hasIndex = true;
                    break;
                }
            }

            if (!$hasIndex) {
                $table->index('nomor_po', 'data_proyek_nomor_po_index');
            }
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
            $table->dropIndex('data_proyek_nomor_po_index');
        });
    }
};
