<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get user profile.
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
                'email' => $user->email,
                'nik' => $user->nik,
                'role' => $user->peran,
                'photo' => $photoUrl,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }

    /**
     * Update user profile (name, email).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:pengguna,email,' . $user->id,
            'nik' => 'sometimes|nullable|string|max:20',
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
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('nik')) {
            $updateData['nik'] = $request->nik;
        }

        $user->update($updateData);

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
     * Change user password (when logged in).
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

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
        ]);
    }

    /**
     * Upload profile photo.
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
