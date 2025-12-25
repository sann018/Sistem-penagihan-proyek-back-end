<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    /**
     * [🔐 AUTH_SYSTEM] Daftarkan user baru
     * 
     * @param Request $request - Email, password, nama
     * @return JsonResponse - User data & token atau error
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:pengguna',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nama' => $request->name,
            'email' => $request->email,
            'kata_sandi' => Hash::make($request->password),
            'peran' => 'read_only', // [🔐 AUTH_SYSTEM] Role default user baru: hanya bisa baca
        ]);

        // [🔐 AUTH_SYSTEM] Kirim verifikasi email (opsional - belum aktif)
        // $user->sendEmailVerificationNotification();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,  // Map ke format frontend
                    'email' => $user->email,
                    'role' => $user->peran, // Map ke format frontend
                    'created_at' => $user->dibuat_pada,
                ],
                'token' => $token,
            ]
        ], 201);
    }

    /**
     * [🔐 AUTH_SYSTEM] User login & generate token
     * 
     * @param Request $request - Email & password
     * @return JsonResponse - User data & token atau error
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->kata_sandi)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,  // [🔐 AUTH_SYSTEM] Map kolom ke format frontend
                    'email' => $user->email,
                    'role' => $user->peran, // [🔐 AUTH_SYSTEM] Gunakan untuk permission checking
                    'created_at' => $user->dibuat_pada,
                ],
                'token' => $token,
            ]
        ]);
    }

    /**
     * [🔐 AUTH_SYSTEM] User logout & batalkan token
     * 
     * @param Request $request
     * @return JsonResponse - Success message
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * [🔐 AUTH_SYSTEM] Dapatkan info user yang sedang login
     * 
     * @param Request $request
     * @return JsonResponse - User data
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->nama,
                'email' => $user->email,
                'role' => $user->peran,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }
}
