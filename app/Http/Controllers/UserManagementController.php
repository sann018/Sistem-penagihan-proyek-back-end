<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    /**
     * Get all users (Super Admin only).
     */
    public function index(): JsonResponse
    {
        $users = User::all()->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->nama,
                'email' => $user->email,
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
     * Admin reset password for any user.
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

        return response()->json([
            'success' => true,
            'message' => "Password untuk {$user->nama} berhasil direset"
        ]);
    }

    /**
     * Update user role (Super Admin only).
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

        $user->update(['peran' => $request->role]);

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
}
