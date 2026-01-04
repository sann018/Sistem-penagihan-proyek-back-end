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
            // Kolom prioritas baru dengan enum
            $table->string('priority_level', 20)->nullable()->after('prioritas')
                ->comment('Level prioritas: critical, high, medium, low, none');
            
            $table->string('priority_source', 30)->nullable()->after('priority_level')
                ->comment('Sumber prioritas: manual, auto_deadline, auto_overdue, auto_blocked, system');
            
            $table->string('priority_reason')->nullable()->after('priority_source')
                ->comment('Alasan prioritas diberikan');
            
            $table->integer('priority_score')->default(0)->after('priority_reason')
                ->comment('Score prioritas untuk sorting (0-999)');
            
            $table->unsignedBigInteger('priority_updated_by')->nullable()->after('priority_score')
                ->comment('User yang terakhir update prioritas');
            
            // Index untuk query performa
            $table->index(['priority_level', 'priority_score'], 'idx_priority_sorting');
            $table->index('priority_source', 'idx_priority_source');
        });
        
        // Migrasi data lama ke sistem baru
        DB::statement("
            UPDATE data_proyek 
            SET 
                priority_level = CASE 
                    WHEN prioritas = 1 THEN 'high'
                    WHEN prioritas = 2 THEN 'medium'
                    ELSE 'none'
                END,
                priority_source = CASE 
                    WHEN prioritas = 1 THEN 'manual'
                    WHEN prioritas = 2 THEN 'auto_deadline'
                    ELSE NULL
                END,
                priority_reason = CASE 
                    WHEN prioritas = 1 THEN 'Migrated from manual priority'
                    WHEN prioritas = 2 THEN 'Migrated from auto priority'
                    ELSE NULL
                END,
                priority_score = CASE 
                    WHEN prioritas = 1 THEN 70
                    WHEN prioritas = 2 THEN 50
                    ELSE 0
                END
            WHERE prioritas IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_proyek', function (Blueprint $table) {
            $table->dropIndex('idx_priority_sorting');
            $table->dropIndex('idx_priority_source');
            $table->dropColumn([
                'priority_level',
                'priority_source',
                'priority_reason',
                'priority_score',
                'priority_updated_by',
            ]);
        });
    }
};
