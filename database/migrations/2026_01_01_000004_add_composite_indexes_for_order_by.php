<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ✅ Tambah composite indexes untuk optimasi ORDER BY dan JOIN
     */
    public function up(): void
    {
        Schema::table('data_proyek', function (Blueprint $table) {
            // ✅ Composite index untuk ORDER BY prioritas + dibuat_pada + pid
            if (!$this->hasCompositeIndex('data_proyek', ['prioritas', 'dibuat_pada', 'pid'])) {
                $table->index(['prioritas', 'dibuat_pada', 'pid'], 'idx_priority_created_pk');
            }
            
            // ✅ Composite index untuk ORDER BY prioritas + tanggal_mulai
            if (!$this->hasCompositeIndex('data_proyek', ['prioritas', 'tanggal_mulai'])) {
                $table->index(['prioritas', 'tanggal_mulai'], 'idx_priority_start_date');
            }
        });

        Schema::table('aktivitas_sistem', function (Blueprint $table) {
            // ✅ Composite index untuk JOIN + ORDER BY
            if (!$this->hasCompositeIndex('aktivitas_sistem', ['pengguna_id', 'waktu_aksi'])) {
                $table->index(['pengguna_id', 'waktu_aksi'], 'idx_user_time');
            }
            
            // ✅ Composite index untuk ORDER BY waktu_aksi + id_aktivitas
            if (!$this->hasCompositeIndex('aktivitas_sistem', ['waktu_aksi', 'id_aktivitas'])) {
                $table->index(['waktu_aksi', 'id_aktivitas'], 'idx_time_id_desc');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_proyek', function (Blueprint $table) {
            if ($this->hasCompositeIndex('data_proyek', ['prioritas', 'dibuat_pada', 'pid'])) {
                $table->dropIndex('idx_priority_created_pk');
            }
            if ($this->hasCompositeIndex('data_proyek', ['prioritas', 'tanggal_mulai'])) {
                $table->dropIndex('idx_priority_start_date');
            }
        });

        Schema::table('aktivitas_sistem', function (Blueprint $table) {
            if ($this->hasCompositeIndex('aktivitas_sistem', ['pengguna_id', 'waktu_aksi'])) {
                $table->dropIndex('idx_user_time');
            }
            if ($this->hasCompositeIndex('aktivitas_sistem', ['waktu_aksi', 'id_aktivitas'])) {
                $table->dropIndex('idx_time_id_desc');
            }
        });
    }
    
    /**
     * Check if composite index exists
     */
    private function hasCompositeIndex(string $table, array $columns): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");
        $indexColumns = [];
        
        foreach ($indexes as $index) {
            $indexName = $index->Key_name;
            if (!isset($indexColumns[$indexName])) {
                $indexColumns[$indexName] = [];
            }
            $indexColumns[$indexName][] = $index->Column_name;
        }
        
        foreach ($indexColumns as $indexCols) {
            if ($indexCols === $columns) {
                return true;
            }
        }
        
        return false;
    }
};
