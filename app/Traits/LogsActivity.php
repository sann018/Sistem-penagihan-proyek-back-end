<?php

namespace App\Traits;

use App\Models\AktivitasSistem;
use App\Models\LogAktivitas;
use Illuminate\Database\Eloquent\Model;
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
     * @param int|string|null $idRecordYangDiubah - ID record yang diubah (support string untuk PID)
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
        int|string|null $idRecordYangDiubah = null,
        ?array $dataSebelum = null,
        ?array $dataSesudah = null
    ): Model {
        $user = $request->user();

        $tipeNormalized = strtolower(trim($tipe));
        $tabelNormalized = $tabelYangDiubah ? strtolower(trim($tabelYangDiubah)) : null;

        // Normalize legacy table names
        if ($tabelNormalized === 'penagihan') {
            $tabelNormalized = 'data_proyek';
        }

        // 1) Log akses/navigasi ke log_aktivitas
        $aksesTypes = [
            'login',
            'logout',
            'login_gagal',
            'session_timeout',
            'forgot_password',
            'reset_password',
            'view',
            'list',
            'download',
            'export',
            'search',
            'filter',
            'sort',
            'upload',
            'error',
            'unauthorized_access',
        ];

        if (in_array($tipeNormalized, $aksesTypes, true)) {
            $ua = $request->userAgent();
            $uaInfo = $this->parseUserAgent($ua);

            return LogAktivitas::create([
                'id_pengguna' => $user?->id_pengguna,
                'aksi' => $this->mapTipeToLogAksi($tipeNormalized, $tabelNormalized),
                'deskripsi' => $deskripsi,
                'path' => $request->path(),
                'method' => $request->method(),
                'status_code' => null,
                'alamat_ip' => $request->ip() ?? '0.0.0.0',
                'user_agent' => $ua,
                'device_type' => $uaInfo['device_type'],
                'browser' => $uaInfo['browser'],
                'os' => $uaInfo['os'],
                'waktu_kejadian' => now(),
            ]);
        }

        // 2) Log perubahan data bisnis ke aktivitas_sistem
        $aksiEnum = $this->mapToAktivitasAksiEnum($tipeNormalized, $tabelNormalized, $aksi);

        // Build diff as array of changes
        $detail = null;
        if (is_array($dataSebelum) && is_array($dataSesudah)) {
            $detail = $this->buildDetailPerubahan($dataSebelum, $dataSesudah);
        }

        // Determine target id as string (PID, id_pengguna, dll)
        $idTarget = $idRecordYangDiubah !== null ? (string) $idRecordYangDiubah : 'unknown';

        // Best-effort nama target
        $namaTarget = null;
        if (is_array($dataSesudah)) {
            $namaTarget = $dataSesudah['nama_proyek'] ?? $dataSesudah['nama'] ?? null;
        }

        return AktivitasSistem::create([
            'id_pengguna' => $user?->id_pengguna,
            'aksi' => $aksiEnum,
            'tabel_target' => $tabelNormalized ?? 'unknown',
            'id_target' => $idTarget,
            'nama_target' => $namaTarget,
            'detail_perubahan' => $detail,
            'keterangan' => $deskripsi,
            'alamat_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'waktu_kejadian' => now(),
        ]);
    }

    private function mapTipeToLogAksi(string $tipe, ?string $tabel): string
    {
        return match ($tipe) {
            'login' => 'login',
            'logout' => 'logout',
            'login_gagal' => 'login_gagal',
            'forgot_password' => 'forgot_password',
            'reset_password' => 'reset_password',
            'search' => 'search',
            'filter' => 'filter',
            'sort' => 'sort',
            'download' => 'download_laporan',
            'upload' => 'upload_dokumen',
            'export' => 'export_excel',
            'view' => ($tabel === 'data_proyek' ? 'lihat_detail_proyek' : 'akses_dashboard'),
            'list' => ($tabel === 'data_proyek' ? 'akses_proyek' : 'akses_dashboard'),
            'unauthorized_access' => 'unauthorized_access',
            'error' => 'error',
            default => 'akses_dashboard',
        };
    }

    private function mapToAktivitasAksiEnum(string $tipe, ?string $tabel, string $aksiLabel): string
    {
        // Map legacy types to new enum values in aktivitas_sistem
        if ($tabel === 'pengguna' || $tabel === 'users') {
            return match ($tipe) {
                'create' => 'tambah_pengguna',
                'edit', 'update' => 'ubah_pengguna',
                'delete' => 'hapus_pengguna',
                default => str_contains(strtolower($aksiLabel), 'role') ? 'ubah_role_pengguna' : 'ubah_pengguna',
            };
        }

        if ($tabel === 'data_proyek' || $tabel === 'proyek') {
            return match ($tipe) {
                'create' => 'tambah_proyek',
                'edit', 'update' => 'ubah_proyek',
                'delete' => 'hapus_proyek',
                'import' => 'import_excel',
                default => 'ubah_proyek',
            };
        }

        // Fallback (keep within enum set)
        return match ($tipe) {
            'import' => 'import_excel',
            'restore' => 'restore',
            default => 'bulk_update',
        };
    }

    private function buildDetailPerubahan(array $dataSebelum, array $dataSesudah): array
    {
        $changes = [];

        $keys = array_unique(array_merge(array_keys($dataSebelum), array_keys($dataSesudah)));
        foreach ($keys as $field) {
            $oldValue = $dataSebelum[$field] ?? null;
            $newValue = $dataSesudah[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'nilai_lama' => $oldValue,
                    'nilai_baru' => $newValue,
                ];
            }
        }

        return $changes;
    }

    private function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return ['device_type' => null, 'browser' => null, 'os' => null];
        }

        $ua = strtolower($userAgent);

        // Device type
        $deviceType = null;
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            $deviceType = 'tablet';
        } elseif (str_contains($ua, 'mobi') || str_contains($ua, 'android') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod')) {
            $deviceType = 'mobile';
        } else {
            $deviceType = 'desktop';
        }

        // Browser
        $browser = null;
        if (str_contains($ua, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'chrome/') && !str_contains($ua, 'edg/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/')) {
            $browser = 'Safari';
        }

        // OS
        $os = null;
        if (str_contains($ua, 'windows nt')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'mac os x')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        }

        return ['device_type' => $deviceType, 'browser' => $browser, 'os' => $os];
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
