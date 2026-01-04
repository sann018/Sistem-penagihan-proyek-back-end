<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel NOTIFIKASI untuk sistem notifikasi otomatis
     * (jatuh tempo, lunas, revisi, dll.)
     */
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            // Primary Key
            $table->id('id_notifikasi');
            
            // Foreign Key ke pengguna
            $table->bigInteger('id_penerima')->unsigned();
            $table->foreign('id_penerima')
                  ->references('id_pengguna')
                  ->on('pengguna')
                  ->onDelete('cascade');
            
            // Konten Notifikasi
            $table->string('judul', 200);
            $table->text('isi_notifikasi');
            
            // Jenis Notifikasi (ENUM)
            $table->enum('jenis_notifikasi', [
                'jatuh_tempo',          // Notifikasi penagihan jatuh tempo
                'h_minus_7',            // 7 hari sebelum jatuh tempo
                'h_minus_3',            // 3 hari sebelum jatuh tempo
                'h_minus_1',            // 1 hari sebelum jatuh tempo
                'lunas',                // Penagihan lunas
                'revisi_mitra',         // Revisi dari mitra
                'status_berubah',       // Status proyek berubah
                'proyek_baru',          // Proyek baru ditambahkan
                'prioritas_berubah',    // Prioritas proyek berubah
                'reminder',             // Reminder umum
                'info',                 // Informasi umum
                'warning',              // Peringatan
                'error',                // Error/masalah sistem
            ])->default('info');
            
            // Status Notifikasi (ENUM)
            $table->enum('status', [
                'pending',      // Belum dikirim
                'terkirim',     // Sudah dikirim
                'dibaca',       // Sudah dibaca user
                'gagal',        // Gagal terkirim
            ])->default('pending');
            
            // Link/Referensi ke data terkait
            $table->string('link_terkait', 500)->nullable();  // URL atau route
            $table->string('referensi_tabel', 100)->nullable();  // Nama tabel (data_proyek, pengguna, dll)
            $table->string('referensi_id', 100)->nullable();  // ID record terkait (PID, id_pengguna, dll)
            
            // Metadata
            $table->json('metadata')->nullable();  // Data tambahan dalam JSON
            $table->integer('prioritas')->default(1);  // 1=Low, 2=Medium, 3=High, 4=Urgent
            
            // Timestamps
            $table->timestamp('waktu_dibuat')->useCurrent();
            $table->timestamp('waktu_dikirim')->nullable();
            $table->timestamp('waktu_dibaca')->nullable();
            $table->timestamp('waktu_kadaluarsa')->nullable();  // Auto-delete setelah waktu ini
            
            // Soft Delete
            $table->softDeletes('dihapus_pada');
            
            // Indexes untuk performance
            $table->index('id_penerima', 'idx_penerima');
            $table->index('jenis_notifikasi', 'idx_jenis');
            $table->index('status', 'idx_status');
            $table->index('waktu_dibuat', 'idx_waktu_dibuat');
            $table->index(['referensi_tabel', 'referensi_id'], 'idx_referensi');
            
            // Composite index untuk query umum
            $table->index(['id_penerima', 'status', 'waktu_dibuat'], 'idx_penerima_status_waktu');
            $table->index(['jenis_notifikasi', 'status'], 'idx_jenis_status');
        });
        
        // Add comment to table
        DB::statement("ALTER TABLE notifikasi COMMENT 'Tabel notifikasi untuk sistem notifikasi otomatis (jatuh tempo, lunas, revisi, dll.)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
