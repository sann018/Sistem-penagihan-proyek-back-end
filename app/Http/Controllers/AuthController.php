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
            'peran' => 'viewer', // Role default: viewer (read-only)
            'email_terverifikasi_pada' => null, // Belum terverifikasi
        ]);

        // [🔐 AUTH_SYSTEM] TODO: Kirim OTP ke email untuk verifikasi
        // Generate OTP 6 digit
        // Send email dengan OTP
        // User harus verify dulu sebelum bisa login

        // TEMPORARY: Auto-generate token (nanti harus verify email dulu)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Silakan verifikasi email Anda.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->peran,
                    'photo' => $user->foto ? url('storage/' . $user->foto) : null,
                    'email_verified' => false,
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
            'identifier' => 'required|string', // Support email or username
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user by email only (username column doesn't exist)
        $user = User::where('email', $request->identifier)->first();

        if (!$user || !Hash::check($request->password, $user->kata_sandi)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        // Update last login timestamp
        $user->update(['terakhir_login_pada' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->peran,
                    'photo' => $user->foto ? url('storage/' . $user->foto) : null,
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
                'photo' => $user->foto ? url('storage/' . $user->foto) : null,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }
}
