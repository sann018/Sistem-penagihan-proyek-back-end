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
        Schema::create('penagihan', function (Blueprint $table) {
            $table->id();
            
            // Informasi Proyek
            $table->string('nama_proyek');
            $table->string('nama_mitra');
            $table->string('pid')->unique();
            $table->string('nomor_po');
            $table->string('phase');
            
            // Status Teknis
            $table->string('status_ct')->default('BELUM CT');
            $table->string('status_ut')->default('BELUM UT');
            
            // Nilai & Material
            $table->decimal('rekon_nilai', 15, 2);
            $table->string('rekon_material')->default('BELUM REKON');
            $table->string('pelurusan_material')->default('BELUM LURUS');
            
            // Status Procurement
            $table->string('status_procurement')->default('ANTRI PERIV');
            
            // Metadata (tidak tampil di UI utama)
            $table->string('status')->default('pending'); // pending, dibayar, terlambat, batal
            $table->date('tanggal_invoice')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->text('catatan')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penagihan');
    }
};
