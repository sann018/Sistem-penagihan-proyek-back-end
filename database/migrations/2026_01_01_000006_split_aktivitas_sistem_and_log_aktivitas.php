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
     * Memisahkan aktivitas_sistem menjadi 2 tabel:
     * 1. log_aktivitas - untuk aktivitas akses & navigasi (login, logout, download, dll)
     * 2. aktivitas_sistem - untuk perubahan data bisnis (CRUD operations)
     */
    public function up(): void
    {
        // ===============================================
        // 1. BUAT TABEL log_aktivitas (Aktivitas Akses)
        // ===============================================
        Schema::create('log_aktivitas', function (Blueprint $table) {
            // Primary Key
            $table->id('id_log');
            
            // Foreign Key ke pengguna (nullable untuk anonymous access)
            $table->bigInteger('id_pengguna')->unsigned()->nullable();
            $table->foreign('id_pengguna')
                  ->references('id_pengguna')
                  ->on('pengguna')
                  ->onDelete('set null');
            
            // Informasi Aksi
            $table->enum('aksi', [
                // Authentication
                'login',
                'logout',
                'login_gagal',
                'session_timeout',
                'forgot_password',
                'reset_password',
                
                // Navigation
                'akses_dashboard',
                'akses_proyek',
                'akses_laporan',
                'akses_pengguna',
                'akses_notifikasi',
                'akses_profile',
                
                // Data Access
                'lihat_detail_proyek',
                'lihat_statistik',
                'download_laporan',
                'export_excel',
                'export_pdf',
                'download_dokumen',
                'upload_dokumen',
                
                // Search & Filter
                'search',
                'filter',
                'sort',
                
                // Other
                'error',
                'unauthorized_access',
            ]);
            
            // Detail Aksi
            $table->text('deskripsi')->nullable();  // Detail tambahan
            $table->string('path', 500)->nullable();  // URL/route yang diakses
            $table->string('method', 10)->nullable();  // GET, POST, PUT, DELETE
            $table->integer('status_code')->nullable();  // HTTP status code (200, 404, 500, dll)
            
            // Informasi Request
            $table->string('alamat_ip', 45);  // Support IPv4 dan IPv6
            $table->text('user_agent')->nullable();  // Browser/device info
            $table->string('device_type', 50)->nullable();  // mobile, desktop, tablet
            $table->string('browser', 100)->nullable();  // Chrome, Firefox, Safari, dll
            $table->string('os', 100)->nullable();  // Windows, MacOS, Linux, Android, iOS
            
            // Session Info
            $table->string('session_id', 100)->nullable();
            
            // Geolocation (optional)
            $table->string('negara', 100)->nullable();
            $table->string('kota', 100)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();  // Data tambahan dalam JSON
            
            // Timestamp
            $table->timestamp('waktu_kejadian')->useCurrent();
            
            // Indexes untuk performance
            $table->index('id_pengguna', 'idx_pengguna');
            $table->index('aksi', 'idx_aksi');
            $table->index('waktu_kejadian', 'idx_waktu');
            $table->index('alamat_ip', 'idx_ip');
            $table->index('session_id', 'idx_session');
            
            // Composite indexes untuk query umum
            $table->index(['id_pengguna', 'waktu_kejadian'], 'idx_pengguna_waktu');
            $table->index(['aksi', 'waktu_kejadian'], 'idx_aksi_waktu');
            $table->index(['alamat_ip', 'waktu_kejadian'], 'idx_ip_waktu');
        });
        
        // Add comment
        DB::statement("ALTER TABLE log_aktivitas COMMENT 'Log aktivitas akses dan navigasi pengguna (login, logout, download, dll.)'");
        
        // ===============================================
        // 2. RENAME aktivitas_sistem → aktivitas_sistem_old
        // ===============================================
        Schema::rename('aktivitas_sistem', 'aktivitas_sistem_old');
        
        // ===============================================
        // 3. BUAT TABEL aktivitas_sistem BARU (Perubahan Data)
        // ===============================================
        Schema::create('aktivitas_sistem', function (Blueprint $table) {
            // Primary Key
            $table->id('id_aktivitas');
            
            // Foreign Key ke pengguna
            $table->bigInteger('id_pengguna')->unsigned()->nullable();
            $table->foreign('id_pengguna')
                  ->references('id_pengguna')
                  ->on('pengguna')
                  ->onDelete('set null');
            
            // Informasi Aksi CRUD
            $table->enum('aksi', [
                // Project Management
                'tambah_proyek',
                'ubah_proyek',
                'hapus_proyek',
                'ubah_status_proyek',
                'ubah_prioritas_proyek',
                
                // User Management (only super_admin)
                'tambah_pengguna',
                'ubah_pengguna',
                'hapus_pengguna',
                'ubah_role_pengguna',
                'reset_password_pengguna',
                
                // Status Updates
                'ubah_status_ct',
                'ubah_status_ut',
                'ubah_rekap_boq',
                'ubah_status_procurement',
                
                // Import/Export
                'import_excel',
                'import_csv',
                
                // Bulk Operations
                'bulk_update',
                'bulk_delete',
                
                // Other
                'restore',  // Restore soft deleted
                'force_delete',  // Permanent delete
            ]);
            
            // Target Data yang Diubah
            $table->string('tabel_target', 100);  // Nama tabel (data_proyek, pengguna, dll)
            $table->string('id_target', 100);  // ID record yang diubah (PID, id_pengguna, dll)
            $table->string('nama_target', 255)->nullable();  // Nama/label record (nama proyek, nama user)
            
            // Detail Perubahan (JSON)
            $table->json('detail_perubahan')->nullable();
            // Format: {"field": "status", "nilai_lama": "pending", "nilai_baru": "approved"}
            // atau untuk multiple fields: [
            //   {"field": "status", "nilai_lama": "...", "nilai_baru": "..."},
            //   {"field": "prioritas", "nilai_lama": 1, "nilai_baru": 2}
            // ]
            
            // Metadata
            $table->text('keterangan')->nullable();  // Keterangan tambahan
            $table->string('alamat_ip', 45)->nullable();  // IP address
            $table->text('user_agent')->nullable();  // Browser info
            
            // Timestamp
            $table->timestamp('waktu_kejadian')->useCurrent();
            
            // Indexes untuk performance
            $table->index('id_pengguna', 'idx_pengguna');
            $table->index('aksi', 'idx_aksi');
            $table->index('tabel_target', 'idx_tabel');
            $table->index('id_target', 'idx_id_target');
            $table->index('waktu_kejadian', 'idx_waktu');
            
            // Composite indexes untuk query umum
            $table->index(['id_pengguna', 'waktu_kejadian'], 'idx_pengguna_waktu');
            $table->index(['tabel_target', 'id_target'], 'idx_tabel_id');
            $table->index(['tabel_target', 'aksi'], 'idx_tabel_aksi');
            $table->index(['aksi', 'waktu_kejadian'], 'idx_aksi_waktu');
        });
        
        // Add comment
        DB::statement("ALTER TABLE aktivitas_sistem COMMENT 'Log perubahan data bisnis (CRUD operations pada proyek, pengguna, dll.)'");
        
        // ===============================================
        // 4. MIGRATE DATA dari aktivitas_sistem_old
        // ===============================================
        
        // Ambil data lama
        $oldData = DB::table('aktivitas_sistem_old')->get();
        
        foreach ($oldData as $row) {
            // Tentukan kategori berdasarkan tipe
            $isDataChange = in_array($row->tipe, ['create', 'update', 'delete', 'restore']);
            
            if ($isDataChange) {
                // Insert ke aktivitas_sistem (perubahan data)
                // Build detail_perubahan from old data_sebelum and data_sesudah
                $detailPerubahan = null;
                if ($row->data_sebelum && $row->data_sesudah) {
                    $before = json_decode($row->data_sebelum, true);
                    $after = json_decode($row->data_sesudah, true);
                    
                    if (is_array($before) && is_array($after)) {
                        $changes = [];
                        foreach ($after as $field => $newValue) {
                            $oldValue = $before[$field] ?? null;
                            if ($oldValue !== $newValue) {
                                $changes[] = [
                                    'field' => $field,
                                    'nilai_lama' => $oldValue,
                                    'nilai_baru' => $newValue,
                                ];
                            }
                        }
                        $detailPerubahan = empty($changes) ? null : json_encode($changes);
                    }
                }
                
                DB::table('aktivitas_sistem')->insert([
                    'id_pengguna' => $row->pengguna_id,
                    'aksi' => $this->mapOldTypeToNewAction($row->tipe, $row->tabel_yang_diubah),
                    'tabel_target' => $row->tabel_yang_diubah ?? 'unknown',
                    'id_target' => (string)($row->id_record_yang_diubah ?? 'unknown'),
                    'nama_target' => $row->nama_pengguna ?? null,
                    'detail_perubahan' => $detailPerubahan,
                    'keterangan' => $row->deskripsi ?? null,
                    'alamat_ip' => $row->ip_address ?? null,
                    'user_agent' => $row->user_agent ?? null,
                    'waktu_kejadian' => $row->waktu_aksi,
                ]);
            } else {
                // Insert ke log_aktivitas (akses/navigasi)
                DB::table('log_aktivitas')->insert([
                    'id_pengguna' => $row->pengguna_id,
                    'aksi' => $this->mapOldTypeToLogAction($row->tipe),
                    'deskripsi' => $row->deskripsi ?? null,
                    'path' => null,
                    'method' => null,
                    'status_code' => null,
                    'alamat_ip' => $row->ip_address ?? '0.0.0.0',  // Use existing or default
                    'user_agent' => $row->user_agent ?? null,
                    'device_type' => null,
                    'browser' => null,
                    'os' => null,
                    'session_id' => null,
                    'negara' => null,
                    'kota' => null,
                    'metadata' => null,
                    'waktu_kejadian' => $row->waktu_aksi,
                ]);
            }
        }
    }

    /**
     * Map old 'tipe' to new 'aksi' for aktivitas_sistem
     */
    private function mapOldTypeToNewAction($tipe, $tabel = null)
    {
        $map = [
            'create' => 'tambah_proyek',
            'update' => 'ubah_proyek',
            'delete' => 'hapus_proyek',
            'restore' => 'restore',
        ];
        
        if ($tabel === 'pengguna' || $tabel === 'users') {
            $map = [
                'create' => 'tambah_pengguna',
                'update' => 'ubah_pengguna',
                'delete' => 'hapus_pengguna',
            ];
        }
        
        return $map[$tipe] ?? 'ubah_proyek';
    }
    
    /**
     * Map old 'tipe' to new 'aksi' for log_aktivitas
     */
    private function mapOldTypeToLogAction($tipe)
    {
        $map = [
            'view' => 'lihat_detail_proyek',
            'list' => 'akses_proyek',
            'login' => 'login',
            'logout' => 'logout',
            'download' => 'download_laporan',
            'export' => 'export_excel',
        ];
        
        return $map[$tipe] ?? 'akses_dashboard';
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('aktivitas_sistem');
        Schema::dropIfExists('log_aktivitas');
        
        // Restore old table
        Schema::rename('aktivitas_sistem_old', 'aktivitas_sistem');
    }
};
