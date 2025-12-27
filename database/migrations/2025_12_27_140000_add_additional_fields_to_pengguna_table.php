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
        Schema::table('pengguna', function (Blueprint $table) {
            // Username sudah ada, hanya tambahkan 3 field baru
            $table->string('jobdesk')->after('nik')->nullable();
            $table->string('mitra')->after('jobdesk')->nullable();
            $table->string('nomor_hp', 20)->after('mitra')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropColumn(['jobdesk', 'mitra', 'nomor_hp']);
        });
    }
};
