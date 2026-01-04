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
     * Migration khusus untuk mengubah tabel penagihan menjadi data_proyek
     * dengan pid sebagai primary key
     */
    public function up(): void
    {
        // Pastikan tabel penagihan ada
        if (!Schema::hasTable('penagihan')) {
            throw new \Exception('Tabel penagihan tidak ditemukan!');
        }
        
        // Pastikan tabel data_proyek belum ada
        if (Schema::hasTable('data_proyek')) {
            throw new \Exception('Tabel data_proyek sudah ada!');
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Step 1: Hapus auto_increment dari kolom id
        DB::statement('ALTER TABLE `penagihan` MODIFY `id` BIGINT UNSIGNED NOT NULL');
        
        // Step 2: Drop primary key
        Schema::table('penagihan', function (Blueprint $table) {
            $table->dropPrimary();
        });
        
        // Step 3: Hapus kolom id
        Schema::table('penagihan', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        
        // Step 4: Rename tabel
        Schema::rename('penagihan', 'data_proyek');
        
        // Step 5: Set pid sebagai primary key
        DB::statement('ALTER TABLE `data_proyek` MODIFY `pid` VARCHAR(255) NOT NULL PRIMARY KEY');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Kembalikan primary key pid
        Schema::table('data_proyek', function (Blueprint $table) {
            $table->dropPrimary();
        });
        
        // Tambahkan kolom id kembali
        DB::statement('ALTER TABLE `data_proyek` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        
        // Rename tabel kembali
        Schema::rename('data_proyek', 'penagihan');
        
        // Kembalikan pid ke unique
        DB::statement('ALTER TABLE `penagihan` MODIFY `pid` VARCHAR(255) NOT NULL UNIQUE');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
