<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AktivitasSistem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    /**
     * [🔐 AUTH_SYSTEM] Daftarkan user baru (SUPER ADMIN ONLY)
     * 
     * @param Request $request - Email, password, nama, role, nik
     * @return JsonResponse - User data atau error
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:pengguna',
            'password' => 'required|string|min:8|confirmed',
            'nik' => 'nullable|string|max:20',
            'role' => 'required|in:super_admin,admin,viewer',
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
            'nik' => $request->nik,
            'peran' => $request->role,
            'email_terverifikasi_pada' => now(), // Internal app, auto-verify
        ]);

        // [🔐 AUTH_SYSTEM] Log aktivitas registrasi user baru oleh Super Admin
        $this->logActivity(
            $request,
            'Registrasi User Baru',
            'create',
            "Super Admin mendaftarkan user baru: {$user->nama} ({$user->email}) dengan role {$user->peran}",
            'pengguna',
            $user->id,
            null,
            [
                'nama' => $user->nama,
                'email' => $user->email,
                'nik' => $user->nik,
                'peran' => $user->peran,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'User berhasil didaftarkan',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,
                    'email' => $user->email,
                    'nik' => $user->nik,
                    'role' => $user->peran,
                    'photo' => $user->foto ? url('storage/' . $user->foto) : null,
                    'created_at' => $user->dibuat_pada,
                ],
            ]
        ], 201);
    }

    /**
     * [🔐 AUTH_SYSTEM] User login & generate token
     * Support login via email (username tidak ada sebagai kolom terpisah)
     * 
     * @param Request $request - Email/identifier & password
     * @return JsonResponse - User data & token atau error
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string', // Email yang digunakan sebagai identifier
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // [🔐 AUTH_SYSTEM] Find user by email (username column doesn't exist)
        $user = User::where('email', $request->identifier)->first();

        if (!$user || !Hash::check($request->password, $user->kata_sandi)) {
            // [🔐 AUTH_SYSTEM] Log failed login attempt
            Log::warning('[🔐 AUTH_SYSTEM] Login gagal', [
                'identifier' => $request->identifier,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        // [🔐 AUTH_SYSTEM] Update last login timestamp
        $user->update(['terakhir_login_pada' => now()]);

        // [🔐 AUTH_SYSTEM] Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // [🔐 AUTH_SYSTEM] Log successful login (manual logging tanpa Request user)
        AktivitasSistem::create([
            'pengguna_id' => $user->id,
            'nama_pengguna' => $user->nama,
            'aksi' => 'Login',
            'tipe' => 'login',
            'deskripsi' => "User {$user->nama} berhasil login",
            'tabel_yang_diubah' => 'auth',
            'id_record_yang_diubah' => $user->id,
            'data_sebelum' => null,
            'data_sesudah' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'waktu_aksi' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,
                    'username' => $user->username ?? $user->email,
                    'email' => $user->email,
                    'role' => $user->peran,
                    'nik' => $user->nik,
                    'jobdesk' => $user->jobdesk,
                    'mitra' => $user->mitra,
                    'nomor_hp' => $user->nomor_hp,
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
        $user = $request->user();
        
        // [🔐 AUTH_SYSTEM] Log logout activity
        $this->logActivity(
            $request,
            'Logout',
            'logout',
            "User {$user->nama} logout",
            'auth',
            $user->id,
            null,
            null
        );
        
        // [🔐 AUTH_SYSTEM] Delete current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
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
                'nik' => $user->nik,
                'photo' => $user->foto ? url('storage/' . $user->foto) : null,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }
}
