<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
        $users = User::all()->map(function($user) {
            // Generate full URL for photo if exists
            $photoUrl = null;
            if ($user->foto) {
                $photoUrl = url('storage/' . $user->foto);
            }
            
            return [
                'id' => $user->id,
                'name' => $user->nama,
                'username' => $user->username ?? $user->email,
                'email' => $user->email,
                'nik' => $user->nik,
                'jobdesk' => $user->jobdesk,
                'mitra' => $user->mitra,
                'nomor_hp' => $user->nomor_hp,
                'photo' => $photoUrl,
                'role' => $user->peran,
                'created_at' => $user->dibuat_pada,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Reset password user oleh Super Admin
     * Generate password baru dan log aktivitasnya
     */
    public function resetUserPassword(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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
     * Bisa update nama, email, NIK dengan audit trail lengkap
     */
    public function update(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:pengguna,email,' . $userId,
            'nik' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('nik')) {
            $updateData['nik'] = $request->nik;
        }

        // Store old data for audit
        $dataSebelum = [
            'nama' => $user->nama,
            'email' => $user->email,
            'nik' => $user->nik,
        ];
        
        $user->update($updateData);
        
        // Store new data for audit
        $dataSesudah = [
            'nama' => $user->nama,
            'email' => $user->email,
            'nik' => $user->nik,
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
                'id' => $user->id,
                'name' => $user->nama,
                'email' => $user->email,
                'nik' => $user->nik,
                'role' => $user->peran,
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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
        
        $user->update(['peran' => $request->role]);

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
                'id' => $user->id,
                'nama' => $user->nama,
                'email' => $user->email,
                'peran' => $user->peran,
            ]
        ]);
    }

    /**
     * [👥 USER_MANAGEMENT] Hapus user (Super Admin only)
     * Delete user dari sistem dengan audit trail
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
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri'
            ], 403);
        }

        $userName = $user->nama;
        $userEmail = $user->email;

        // Log activity sebelum delete
        $this->logActivity(
            $request,
            'Hapus User',
            'delete',
            "Menghapus user: {$userName} ({$userEmail})",
            'pengguna',
            $user->id,
            [
                'nama' => $user->nama,
                'email' => $user->email,
                'nik' => $user->nik,
                'peran' => $user->peran,
            ],
            null
        );

        // Delete foto jika ada
        if ($user->foto && \Storage::exists('public/' . $user->foto)) {
            \Storage::delete('public/' . $user->foto);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User {$userName} berhasil dihapus"
        ]);
    }
}
