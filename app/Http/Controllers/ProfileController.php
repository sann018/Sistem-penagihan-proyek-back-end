<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;

class ProfileController extends Controller
{
    use LogsActivity;
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
                'id' => $user->id,
                'name' => $user->nama,
                'username' => $user->username ?? $user->email,
                'email' => $user->email,
                'nik' => $user->nik,
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
     * [👤 PROFILE_MANAGEMENT] Update profil user (nama, email, NIK)
     * Menyimpan perubahan data personal dengan audit trail
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|nullable|string|max:50|unique:pengguna,username,' . $user->id,
            'email' => 'sometimes|required|email|unique:pengguna,email,' . $user->id,
            'nik' => 'sometimes|nullable|string|max:20',
            'jobdesk' => 'sometimes|nullable|string|max:255',
            'mitra' => 'sometimes|nullable|string|max:255',
            'nomor_hp' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update dengan kolom bahasa Indonesia
        $updateData = [];
        if ($request->has('name')) {
            $updateData['nama'] = $request->name;
        }
        if ($request->has('username')) {
            $updateData['username'] = $request->username;
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('nik')) {
            $updateData['nik'] = $request->nik;
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

        // Store old data
        $dataSebelum = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'nik' => $user->nik,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
            'nomor_hp' => $user->nomor_hp,
        ];
        
        $user->update($updateData);
        
        // Store new data
        $dataSesudah = [
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'nik' => $user->nik,
            'jobdesk' => $user->jobdesk,
            'mitra' => $user->mitra,
            'nomor_hp' => $user->nomor_hp,
        ];

        // Log activity
        $this->logActivity(
            $request,
            'Edit Profil',
            'edit',
            "Mengubah informasi profil",
            'pengguna',
            $user->id,
            $dataSebelum,
            $dataSesudah
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diupdate',
            'data' => [
                'id' => $user->id,
                'name' => $user->nama,
                'email' => $user->email,
                'nik' => $user->nik,
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
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->kata_sandi)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai'
            ], 400);
        }

        // Update password
        $user->update([
            'kata_sandi' => Hash::make($request->password)
        ]);

        // Log activity
        $this->logActivity(
            $request,
            'Ubah Password',
            'edit',
            "Mengubah password akun",
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
     * Mendukung JPEG, PNG, GIF dengan maksimal 1MB
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:1024', // max 1MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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
