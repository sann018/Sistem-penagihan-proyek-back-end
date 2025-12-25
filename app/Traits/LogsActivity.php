<?php

namespace App\Traits;

use App\Models\AktivitasSistem;
use Illuminate\Http\Request;

trait LogsActivity
{
    /**
     * [🔄 LOGGING_HELPER] Log aktivitas user ke database
     * Menyimpan aksi, deskripsi, data sebelum-sesudah, IP, user agent
     * 
     * @param Request $request
     * @param string $aksi - Label aksi ("Tambah Proyek", "Edit User", "Login")
     * @param string $tipe - Tipe aksi (login, create, edit, delete, export, import)
     * @param string $deskripsi - Deskripsi detail perubahan
     * @param string|null $tabelYangDiubah - Nama tabel (penagihan, pengguna, dll)
     * @param int|null $idRecordYangDiubah - ID record yang diubah
     * @param array|null $dataSebelum - Data lama untuk audit trail
     * @param array|null $dataSesudah - Data baru untuk audit trail
     * @return AktivitasSistem
     */
    protected function logActivity(
        Request $request,
        string $aksi,
        string $tipe,
        string $deskripsi,
        ?string $tabelYangDiubah = null,
        ?int $idRecordYangDiubah = null,
        ?array $dataSebelum = null,
        ?array $dataSesudah = null
    ): AktivitasSistem {
        $user = $request->user();

        return AktivitasSistem::create([
            'pengguna_id' => $user->id,
            'nama_pengguna' => $user->nama,
            'aksi' => $aksi,
            'tipe' => $tipe,
            'deskripsi' => $deskripsi,
            'tabel_yang_diubah' => $tabelYangDiubah,
            'id_record_yang_diubah' => $idRecordYangDiubah,
            'data_sebelum' => $dataSebelum,
            'data_sesudah' => $dataSesudah,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'waktu_aksi' => now(),
        ]);
    }

    /**
     * [🔄 LOGGING_HELPER] Konversi tipe aksi ke label dalam Bahasa Indonesia
     */
    protected function getActivityTypeLabel(string $tipe): string
    {
        $labels = [
            'login' => 'Login',
            'create' => 'Tambah',
            'edit' => 'Edit',
            'delete' => 'Hapus',
            'export' => 'Export',
            'import' => 'Import',
            'upload' => 'Upload',
            'download' => 'Download',
        ];

        return $labels[$tipe] ?? ucfirst($tipe);
    }

    /**
     * Sanitize data for logging (remove sensitive fields)
     */
    protected function sanitizeDataForLog(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'api_key', 'secret'];
        
        return collect($data)->except($sensitiveFields)->toArray();
    }
}
