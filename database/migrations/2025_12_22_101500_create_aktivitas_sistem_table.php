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
        Schema::create('aktivitas_sistem', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pengguna_id');
            $table->string('nama_pengguna'); // Store user name for historical purposes
            $table->string('aksi'); // Login, Create, Edit, Delete, Export, etc
            $table->string('tipe'); // login, create, edit, delete
            $table->text('deskripsi'); // Detailed description
            $table->string('tabel_yang_diubah')->nullable(); // e.g., penagihan, pengguna
            $table->unsignedBigInteger('id_record_yang_diubah')->nullable(); // e.g., project id
            $table->json('data_sebelum')->nullable(); // Old data for audit trail
            $table->json('data_sesudah')->nullable(); // New data for audit trail
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('waktu_aksi');
            $table->timestamps();

            // Foreign key
            $table->foreign('pengguna_id')
                ->references('id')
                ->on('pengguna')
                ->onDelete('cascade');

            // Indexes for better query performance
            $table->index('pengguna_id');
            $table->index('tipe');
            $table->index('waktu_aksi');
            $table->index(['tabel_yang_diubah', 'id_record_yang_diubah']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aktivitas_sistem');
    }
};
