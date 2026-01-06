<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    use LogsActivity;

    /**
     * [\ud83d\udcde PUBLIC] Kontak Super Admin untuk halaman login (tanpa auth).
     * Mengambil nomor_hp dari user dengan peran super_admin.
     */
    public function superAdminContact(): JsonResponse
    {
        try {
            $superAdmin = User::query()
                ->where('peran', 'super_admin')
                ->orderBy('dibuat_pada', 'asc')
                ->first();

            if (!$superAdmin) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'nomor_hp' => null,
                        'name' => null,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'nomor_hp' => $superAdmin->nomor_hp,
                    'name' => $superAdmin->nama,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil kontak super admin: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * [👤 PROFILE_MANAGEMENT] Tampilkan profil user yang sedang login
     * Include foto URL, role, dan info personal
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Generate full URL for photo if exists
        $photoUrl = null;
        if ($user->foto) {
            // Gunakan url() untuk full URL dengan domain
            $photoUrl = url('storage/' . $user->foto);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id_pengguna,
                'name' => $user->nama,
                'username' => $user->username ?? $user->email,
                'email' => $user->email,
                'jobdesk' => $user->jobdesk,
                'mitra' => $user->mitra,
                'nomor_hp' => $user->nomor_hp,
                'role' => $user->peran,
                'photo' => $photoUrl,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }

    /**
    * [👤 PROFILE_MANAGEMENT] Update profil user (nama, email)
     * Menyimpan perubahan data personal dengan audit trail
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $user->id_pengguna;

        $role = (string) ($user->peran ?? '');
        $isAdmin = in_array($role, ['super_admin', 'admin'], true);

        // Viewer hanya boleh edit field terbatas
        $allowedFields = $isAdmin
            ? ['name', 'username', 'email', 'jobdesk', 'mitra', 'nomor_hp']
            : ['name', 'jobdesk', 'mitra', 'nomor_hp'];

        $requestKeys = array_keys($request->all());
        $forbiddenKeys = array_values(array_diff($requestKeys, $allowedFields));
        if (!empty($forbiddenKeys)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
                'errors' => [
                    'fields' => ['Field tidak diizinkan: ' . implode(', ', $forbiddenKeys)],
                ],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            // IMPORTANT: PK user menggunakan kolom id_pengguna, bukan id
            'username' => $isAdmin ? [
                'sometimes',
                'nullable',
                'string',
                'min:4',
                'max:30',
                'regex:/^(?=.{4,30}$)(?![._-])(?!.*[._-]{2})[a-zA-Z0-9._-]+(?<![._-])$/',
                'unique:pengguna,username,' . $userKey . ',id_pengguna',
            ] : 'prohibited',
            'email' => $isAdmin
                ? ('sometimes|required|email|unique:pengguna,email,' . $userKey . ',id_pengguna')
                : 'prohibited',
            'jobdesk' => 'sometimes|nullable|string|max:255',
            'mitra' => 'sometimes|nullable|string|max:255',
            'nomor_hp' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataSebelum = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
            'nomor_hp' => $user->nomor_hp,
        ];

        $updateData = [];
        if ($request->has('name')) {
            $updateData['nama'] = $request->name;
        }
        if ($isAdmin && $request->has('username')) {
            $updateData['username'] = $request->username === null
                ? null
                : strtolower(trim((string) $request->username));
        }
        if ($isAdmin && $request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('jobdesk')) {
            $updateData['jobdesk'] = $request->jobdesk;
        }
        if ($request->has('mitra')) {
            $updateData['mitra'] = $request->mitra;
        }
        if ($request->has('nomor_hp')) {
            $updateData['nomor_hp'] = $request->nomor_hp;
        }

        $user->update($updateData);

        $dataSesudah = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
            'nomor_hp' => $user->nomor_hp,
        ];

        $this->logActivity(
            $request,
            'Edit Profil',
            'edit',
            'Mengubah informasi profil',
            'pengguna',
            $user->id,
            $dataSebelum,
            $dataSesudah
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diupdate',
            'data' => [
                'id' => $user->id_pengguna,
                'name' => $user->nama,
                'username' => $user->username,
                'email' => $user->email,
                'jobdesk' => $user->jobdesk,
                'mitra' => $user->mitra,
                'nomor_hp' => $user->nomor_hp,
                'role' => $user->peran,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }

    /**
     * [👤 PROFILE_MANAGEMENT] Ubah password user saat sedang login
     * Validasi password lama sebelum set password baru
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
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

        if (!Hash::check($request->current_password, $user->kata_sandi)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai'
            ], 400);
        }

        $user->update([
            'kata_sandi' => Hash::make($request->password)
        ]);

        $this->logActivity(
            $request,
            'Ubah Password',
            'edit',
            'Mengubah password akun',
            'pengguna',
            $user->id,
            null,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
        ]);
    }

    /**
     * [👤 PROFILE_MANAGEMENT] Upload foto profil user
     * Mendukung JPEG, PNG, GIF dengan maksimal 2MB
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Delete old photo if exists
            if ($user->foto && Storage::disk('public')->exists($user->foto)) {
                Storage::disk('public')->delete($user->foto);
            }

            // Store new photo
            $path = $request->file('photo')->store('profile-photos', 'public');

            // Update user photo path
            $user->update([
                'foto' => $path
            ]);

            // Generate full URL for photo dengan url() agar include domain
            $photoUrl = url('storage/' . $path);

            // Log activity
            $this->logActivity(
                $request,
                'Upload Foto Profil',
                'upload',
                "Mengupload foto profil",
                'pengguna',
                $user->id,
                null,
                ['foto' => $path]
            );

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diupload',
                'data' => [
                    'photo' => $photoUrl
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload foto: ' . $e->getMessage()
            ], 500);
        }
    }
}
