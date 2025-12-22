<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename tabel users ke pengguna
        Schema::rename('users', 'pengguna');
        
        // 2. Rename kolom di tabel pengguna
        Schema::table('pengguna', function (Blueprint $table) {
            $table->renameColumn('name', 'nama');
            $table->renameColumn('email_verified_at', 'email_terverifikasi_pada');
            $table->renameColumn('password', 'kata_sandi');
            $table->renameColumn('role', 'peran');
            $table->renameColumn('remember_token', 'token_ingat');
            $table->renameColumn('created_at', 'dibuat_pada');
            $table->renameColumn('updated_at', 'diperbarui_pada');
        });
        
        // 3. Update kolom di tabel penagihan (sudah bahasa Indonesia, tapi tambahkan konsistensi)
        Schema::table('penagihan', function (Blueprint $table) {
            $table->renameColumn('created_at', 'dibuat_pada');
            $table->renameColumn('updated_at', 'diperbarui_pada');
            $table->renameColumn('deleted_at', 'dihapus_pada');
        });
        
        // 4. Rename tabel password_reset_tokens ke token_reset_kata_sandi
        Schema::rename('password_reset_tokens', 'token_reset_kata_sandi');
        
        // 5. Rename kolom di tabel token_reset_kata_sandi
        Schema::table('token_reset_kata_sandi', function (Blueprint $table) {
            $table->renameColumn('token', 'token');
            $table->renameColumn('created_at', 'dibuat_pada');
        });
        
        // 6. Rename tabel sessions ke sesi
        Schema::rename('sessions', 'sesi');
        
        // 7. Rename kolom di tabel sesi
        Schema::table('sesi', function (Blueprint $table) {
            $table->renameColumn('user_id', 'pengguna_id');
            $table->renameColumn('ip_address', 'alamat_ip');
            $table->renameColumn('user_agent', 'agen_pengguna');
            $table->renameColumn('last_activity', 'aktivitas_terakhir');
        });
        
        // 8. Rename tabel jobs ke pekerjaan (jika ada)
        if (Schema::hasTable('jobs')) {
            Schema::rename('jobs', 'pekerjaan');
            Schema::table('pekerjaan', function (Blueprint $table) {
                $table->renameColumn('queue', 'antrian');
                $table->renameColumn('payload', 'muatan');
                $table->renameColumn('attempts', 'percobaan');
                $table->renameColumn('reserved_at', 'direserve_pada');
                $table->renameColumn('available_at', 'tersedia_pada');
                $table->renameColumn('created_at', 'dibuat_pada');
            });
        }
        
        // 9. Rename tabel cache ke tembolok (jika ada)
        if (Schema::hasTable('cache')) {
            Schema::rename('cache', 'tembolok');
            Schema::table('tembolok', function (Blueprint $table) {
                $table->renameColumn('key', 'kunci');
                $table->renameColumn('value', 'nilai');
                $table->renameColumn('expiration', 'kedaluwarsa');
            });
        }
        
        if (Schema::hasTable('cache_locks')) {
            Schema::rename('cache_locks', 'kunci_tembolok');
            Schema::table('kunci_tembolok', function (Blueprint $table) {
                $table->renameColumn('key', 'kunci');
                $table->renameColumn('owner', 'pemilik');
                $table->renameColumn('expiration', 'kedaluwarsa');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse semua perubahan
        
        // Cache
        if (Schema::hasTable('kunci_tembolok')) {
            Schema::table('kunci_tembolok', function (Blueprint $table) {
                $table->renameColumn('kunci', 'key');
                $table->renameColumn('pemilik', 'owner');
                $table->renameColumn('kedaluwarsa', 'expiration');
            });
            Schema::rename('kunci_tembolok', 'cache_locks');
        }
        
        if (Schema::hasTable('tembolok')) {
            Schema::table('tembolok', function (Blueprint $table) {
                $table->renameColumn('kunci', 'key');
                $table->renameColumn('nilai', 'value');
                $table->renameColumn('kedaluwarsa', 'expiration');
            });
            Schema::rename('tembolok', 'cache');
        }
        
        // Jobs
        if (Schema::hasTable('pekerjaan')) {
            Schema::table('pekerjaan', function (Blueprint $table) {
                $table->renameColumn('antrian', 'queue');
                $table->renameColumn('muatan', 'payload');
                $table->renameColumn('percobaan', 'attempts');
                $table->renameColumn('direserve_pada', 'reserved_at');
                $table->renameColumn('tersedia_pada', 'available_at');
                $table->renameColumn('dibuat_pada', 'created_at');
            });
            Schema::rename('pekerjaan', 'jobs');
        }
        
        // Sessions
        Schema::table('sesi', function (Blueprint $table) {
            $table->renameColumn('pengguna_id', 'user_id');
            $table->renameColumn('alamat_ip', 'ip_address');
            $table->renameColumn('agen_pengguna', 'user_agent');
            $table->renameColumn('aktivitas_terakhir', 'last_activity');
        });
        Schema::rename('sesi', 'sessions');
        
        // Password reset tokens
        Schema::table('token_reset_kata_sandi', function (Blueprint $table) {
            $table->renameColumn('dibuat_pada', 'created_at');
        });
        Schema::rename('token_reset_kata_sandi', 'password_reset_tokens');
        
        // Penagihan
        Schema::table('penagihan', function (Blueprint $table) {
            $table->renameColumn('dibuat_pada', 'created_at');
            $table->renameColumn('diperbarui_pada', 'updated_at');
            $table->renameColumn('dihapus_pada', 'deleted_at');
        });
        
        // Users
        Schema::table('pengguna', function (Blueprint $table) {
            $table->renameColumn('nama', 'name');
            $table->renameColumn('email_terverifikasi_pada', 'email_verified_at');
            $table->renameColumn('kata_sandi', 'password');
            $table->renameColumn('peran', 'role');
            $table->renameColumn('token_ingat', 'remember_token');
            $table->renameColumn('dibuat_pada', 'created_at');
            $table->renameColumn('diperbarui_pada', 'updated_at');
        });
        Schema::rename('pengguna', 'users');
    }
};
