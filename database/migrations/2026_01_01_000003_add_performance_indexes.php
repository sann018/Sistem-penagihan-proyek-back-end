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
     * Menambahkan INDEX untuk optimasi query dan performa
     * Database akan lebih cepat saat search, filter, dan sort
     */
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            // Index untuk search dan authentication
            if (!$this->hasIndex('pengguna', 'email')) $table->index('email');
            if (!$this->hasIndex('pengguna', 'username')) $table->index('username');
            if (!$this->hasIndex('pengguna', 'peran')) $table->index('peran');
            if (!$this->hasIndex('pengguna', 'nik')) $table->index('nik');
        });

        Schema::table('data_proyek', function (Blueprint $table) {
            // Index untuk search
            if (!$this->hasIndex('data_proyek', 'nama_proyek')) $table->index('nama_proyek');
            if (!$this->hasIndex('data_proyek', 'nama_mitra')) $table->index('nama_mitra');
            if (!$this->hasIndex('data_proyek', 'nomor_po')) $table->index('nomor_po');
            
            // Index untuk filter status
            if (!$this->hasIndex('data_proyek', 'status_ct')) $table->index('status_ct');
            if (!$this->hasIndex('data_proyek', 'status_ut')) $table->index('status_ut');
            if (!$this->hasIndex('data_proyek', 'rekap_boq')) $table->index('rekap_boq');
            if (!$this->hasIndex('data_proyek', 'rekon_material')) $table->index('rekon_material');
            if (!$this->hasIndex('data_proyek', 'pelurusan_material')) $table->index('pelurusan_material');
            if (!$this->hasIndex('data_proyek', 'status_procurement')) $table->index('status_procurement');
            if (!$this->hasIndex('data_proyek', 'status')) $table->index('status');
            
            // Index untuk prioritas dan sorting
            if (!$this->hasIndex('data_proyek', 'prioritas')) $table->index('prioritas');
            if (!$this->hasIndex('data_proyek', 'dibuat_pada')) $table->index('dibuat_pada');
            if (!$this->hasIndex('data_proyek', 'diperbarui_pada')) $table->index('diperbarui_pada');
            if (!$this->hasIndex('data_proyek', 'tanggal_mulai')) $table->index('tanggal_mulai');
            if (!$this->hasIndex('data_proyek', 'tanggal_invoice')) $table->index('tanggal_invoice');
            if (!$this->hasIndex('data_proyek', 'tanggal_jatuh_tempo')) $table->index('tanggal_jatuh_tempo');
        });

        Schema::table('aktivitas_sistem', function (Blueprint $table) {
            // Index untuk filter dan search
            if (!$this->hasIndex('aktivitas_sistem', 'pengguna_id')) $table->index('pengguna_id');
            if (!$this->hasIndex('aktivitas_sistem', 'tipe')) $table->index('tipe');
            if (!$this->hasIndex('aktivitas_sistem', 'tabel_yang_diubah')) $table->index('tabel_yang_diubah');
            if (!$this->hasIndex('aktivitas_sistem', 'waktu_aksi')) $table->index('waktu_aksi');
        });
    }
    
    /**
     * Check if index exists
     */
    private function hasIndex(string $table, string $column): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = ?", [$column]);
        return count($indexes) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['username']);
            $table->dropIndex(['peran']);
            $table->dropIndex(['nik']);
        });

        Schema::table('data_proyek', function (Blueprint $table) {
            $table->dropIndex(['nama_proyek']);
            $table->dropIndex(['nama_mitra']);
            $table->dropIndex(['nomor_po']);
            $table->dropIndex(['status_ct']);
            $table->dropIndex(['status_ut']);
            $table->dropIndex(['rekap_boq']);
            $table->dropIndex(['rekon_material']);
            $table->dropIndex(['pelurusan_material']);
            $table->dropIndex(['status_procurement']);
            $table->dropIndex(['status']);
            $table->dropIndex(['prioritas']);
            $table->dropIndex(['dibuat_pada']);
            $table->dropIndex(['diperbarui_pada']);
            $table->dropIndex(['tanggal_mulai']);
            $table->dropIndex(['tanggal_invoice']);
            $table->dropIndex(['tanggal_jatuh_tempo']);
            $table->dropIndex(['prioritas', 'dibuat_pada']);
            $table->dropIndex(['status_procurement', 'prioritas']);
        });

        Schema::table('aktivitas_sistem', function (Blueprint $table) {
            $table->dropIndex(['pengguna_id']);
            $table->dropIndex(['tipe']);
            $table->dropIndex(['tabel_yang_diubah']);
            $table->dropIndex(['waktu_aksi']);
            $table->dropIndex(['pengguna_id', 'waktu_aksi']);
            $table->dropIndex(['tipe', 'waktu_aksi']);
        });
    }
};
