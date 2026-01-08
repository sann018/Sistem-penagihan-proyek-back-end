<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use App\Traits\LogsActivity;

class UserManagementController extends Controller
{
    use LogsActivity;
    /**
     * [👥 USER_MANAGEMENT] Tampilkan list semua user (Super Admin only)
     * Include foto, role, dan info user detail
     */
    public function index(): JsonResponse
    {
        Log::info('[USER_MANAGEMENT] Fetching all users');
        
        $users = User::all();
        Log::info('[USER_MANAGEMENT] Found users count: ' . $users->count());
        
        $mappedUsers = $users->map(function($user) {
            // Generate full URL for photo if exists
            $photoUrl = null;
            if ($user->foto) {
                $photoUrl = url('storage/' . $user->foto);
            }
            
            $mapped = [
                'id' => $user->id_pengguna, // Explicit primary key
                'name' => $user->nama,
                'username' => $user->username ?? $user->email,
                'email' => $user->email,
                'jobdesk' => $user->jobdesk ?? '',
                'mitra' => $user->mitra ?? '',
                'phone' => $user->nomor_hp ?? '', // Match frontend field name
                'photo' => $photoUrl,
                'role' => $user->peran,
                'active' => (bool) ($user->aktif ?? true),
                'created_at' => $user->dibuat_pada ? $user->dibuat_pada->format('Y-m-d\TH:i:s.u\Z') : null,
            ];
            
            return $mapped;
        });

        Log::info('[USER_MANAGEMENT] Sample mapped user: ' . json_encode($mappedUsers->first()));

        return response()->json([
            'success' => true,
            'message' => 'Data users berhasil dimuat',
            'data' => $mappedUsers
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Reset password user oleh Super Admin
     * Generate password baru dan log aktivitasnya
     */
    public function resetUserPassword(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->update([
            'kata_sandi' => Hash::make($request->password)
        ]);

        // Log activity
        $this->logActivity(
            $request,
            'Reset Password User',
            'edit',
            "Mereset password untuk user: {$user->nama}",
            'pengguna',
            $user->id,
            null,
            null
        );

        return response()->json([
            'success' => true,
            'message' => "Password untuk {$user->nama} berhasil direset"
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Update info user (Super Admin only)
    * Bisa update nama, email dengan audit trail lengkap
     */
    public function update(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'min:4',
                'max:30',
                'regex:/^(?=.{4,30}$)(?![._-])(?!.*[._-]{2})[a-zA-Z0-9._-]+(?<![._-])$/',
                'unique:pengguna,username,' . $userId . ',id_pengguna',
            ],
            'email' => 'sometimes|required|email|unique:pengguna,email,' . $userId . ',id_pengguna',
            // Frontend menggunakan field 'keterangan' untuk jobdesk
            'keterangan' => 'sometimes|nullable|string|max:500',
            // Mitra optional untuk user viewer/admin (dipakai untuk akun mitra: viewer + field mitra)
            'mitra' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($value === null) return;
                    $mitra = trim((string) $value);
                    if ($mitra === '') return;

                    if (preg_match('/telkom\s*akses/i', $mitra) === 1) return;

                    $exists = DB::table('data_proyek')
                        ->whereNotNull('nama_mitra')
                        ->whereRaw("TRIM(nama_mitra) != ''")
                        ->where('nama_mitra', $mitra)
                        ->exists();

                    if (!$exists) {
                        $fail('Nama Mitra tidak valid.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $updateData = [];
        if ($request->has('name')) {
            $updateData['nama'] = $request->name;
        }
        if ($request->has('username')) {
            $updateData['username'] = $request->username === null
                ? null
                : strtolower(trim((string) $request->username));
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }

        if ($request->has('keterangan')) {
            $updateData['jobdesk'] = $request->keterangan;
        }

        if ($request->has('mitra')) {
            $mitra = $request->mitra;
            $updateData['mitra'] = is_string($mitra) ? trim($mitra) : null;
        }

        // Store old data for audit
        $dataSebelum = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
        ];
        
        $user->update($updateData);
        
        // Store new data for audit
        $dataSesudah = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
        ];

        // Log activity
        $this->logActivity(
            $request,
            'Edit User',
            'edit',
            "Mengubah informasi user: {$user->nama}",
            'pengguna',
            $user->id,
            $dataSebelum,
            $dataSesudah
        );

        return response()->json([
            'success' => true,
            'message' => 'Informasi user berhasil diupdate',
            'data' => [
                'id' => $user->id_pengguna,
                'name' => $user->nama,
                'username' => $user->username ?? $user->email,
                'email' => $user->email,
                'role' => $user->peran,
                'jobdesk' => $user->jobdesk,
                'mitra' => $user->mitra,
            ]
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Aktifkan / Nonaktifkan akun user (Super Admin only)
     * - Tidak boleh untuk role super_admin
     * - Jika dinonaktifkan: revoke semua token Sanctum
     */
    public function setActive(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if (($user->peran ?? '') === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Super Admin tidak dapat dinonaktifkan.'
            ], 403);
        }

        $active = (bool) $request->boolean('active');

        $dataSebelum = [
            'aktif' => (bool) ($user->aktif ?? true),
        ];

        $user->update([
            'aktif' => $active,
        ]);

        if ($active === false) {
            // Revoke semua token agar user langsung logout
            $user->tokens()->delete();
        }

        $dataSesudah = [
            'aktif' => (bool) ($user->aktif ?? true),
        ];

        $this->logActivity(
            $request,
            $active ? 'Aktifkan Akun User' : 'Nonaktifkan Akun User',
            'edit',
            ($active ? 'Mengaktifkan' : 'Menonaktifkan') . " akun user: {$user->nama}",
            'pengguna',
            $user->id,
            $dataSebelum,
            $dataSesudah
        );

        return response()->json([
            'success' => true,
            'message' => $active ? 'Akun berhasil diaktifkan' : 'Akun berhasil dinonaktifkan',
            'data' => [
                'id' => $user->id_pengguna,
                'active' => (bool) ($user->aktif ?? true),
            ]
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Update role user (Super Admin only)
     * Ubah permission user antara super_admin, admin, atau viewer
     */
    public function updateRole(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:super_admin,admin,viewer',
            'mitra' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($value === null) return;
                    $mitra = trim((string) $value);
                    if ($mitra === '') return;

                    if (preg_match('/telkom\s*akses/i', $mitra) === 1) return;

                    $exists = DB::table('data_proyek')
                        ->whereNotNull('nama_mitra')
                        ->whereRaw("TRIM(nama_mitra) != ''")
                        ->where('nama_mitra', $mitra)
                        ->exists();

                    if (!$exists) {
                        $fail('Nama Mitra tidak valid.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $roleSebelum = $user->peran;

        $updateData = ['peran' => $request->role];
        // Update mitra hanya jika dikirim (akun mitra sekarang: role=viewer + field mitra)
        if ($request->has('mitra')) {
            $updateData['mitra'] = $request->mitra === null ? null : trim((string) $request->mitra);
        }

        $user->update($updateData);

        // Log activity
        $this->logActivity(
            $request,
            'Ubah Role User',
            'edit',
            "Mengubah role user {$user->nama} dari {$roleSebelum} menjadi {$request->role}",
            'pengguna',
            $user->id,
            ['peran' => $roleSebelum],
            ['peran' => $request->role]
        );

        return response()->json([
            'success' => true,
            'message' => "Role {$user->nama} berhasil diubah menjadi {$request->role}",
            'data' => [
                'id' => $user->id_pengguna,
                'nama' => $user->nama,
                'email' => $user->email,
                'peran' => $user->peran,
                'mitra' => $user->mitra,
            ]
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Hapus user (Super Admin only)
     * Delete user dari sistem dengan audit trail
     * HARD DELETE - Data benar-benar dihapus dari database
     */
    public function destroy(Request $request, $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Cegah Super Admin menghapus dirinya sendiri
        $currentUserId = $request->user()->id_pengguna;
        if ($currentUserId === $user->id_pengguna) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri'
            ], 403);
        }

        // Cegah menghapus Super Admin lain
        if ($user->peran === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Super Admin tidak dapat dihapus'
            ], 403);
        }

        $userName = $user->nama;
        $userEmail = $user->email;
        $userId = $user->id_pengguna;

        // Log activity sebelum delete
        $this->logActivity(
            $request,
            'Hapus User',
            'delete',
            "Menghapus user: {$userName} ({$userEmail})",
            'pengguna',
            $userId,
            [
                'id' => $userId,
                'nama' => $user->nama,
                'email' => $user->email,
                'username' => $user->username,
                'peran' => $user->peran,
            ],
            null
        );

        // Delete foto jika ada
        if ($user->foto && Storage::exists('public/' . $user->foto)) {
            Storage::delete('public/' . $user->foto);
        }

        // HARD DELETE - Hapus permanen dari database
        $deleted = $user->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus user dari database'
            ], 500);
        }

        // Verifikasi user benar-benar terhapus
        $checkUser = User::find($userId);
        if ($checkUser) {
            return response()->json([
                'success' => false,
                'message' => 'User masih ada di database setelah penghapusan'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "User {$userName} berhasil dihapus dari sistem",
            'deleted_id' => $userId
        ]);
    }
}
