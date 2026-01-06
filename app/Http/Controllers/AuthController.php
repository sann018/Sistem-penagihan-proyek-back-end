<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AktivitasSistem;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    /**
     * [🔐 AUTH_SYSTEM] Daftarkan user baru (SUPER ADMIN ONLY)
     * 
        * @param Request $request - Email, password, nama, role
     * @return JsonResponse - User data atau error
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // Username:
            // - 4-30 chars
            // - huruf/angka/._-
            // - tidak boleh diawali/diakhiri simbol
            // - tidak boleh ada 2 simbol berurutan
            'username' => [
                'required',
                'string',
                'min:4',
                'max:30',
                'regex:/^(?=.{4,30}$)(?![._-])(?!.*[._-]{2})[a-zA-Z0-9._-]+(?<![._-])$/',
                'unique:pengguna,username',
            ],
            'email' => 'required|string|email|max:255|unique:pengguna',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'role' => 'required|in:super_admin,admin,viewer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $username = strtolower(trim((string) $request->username));

        $user = User::create([
            'nama' => $request->name,
            'email' => $request->email,
            'username' => $username,
            'kata_sandi' => Hash::make($request->password),
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
                    'username' => $user->username,
                    'email' => $user->email,
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
            'identifier' => 'required|string', // Email / Username
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $identifier = trim((string) $request->identifier);

        // [🔐 AUTH_SYSTEM] Find user by email OR username
        // Prioritaskan email match jika identifier berupa email
        $user = null;
        if (str_contains($identifier, '@')) {
            $user = User::query()->where('email', $identifier)->first();
        }
        if (!$user) {
            $user = User::query()
                ->where('username', $identifier)
                ->orWhere('email', $identifier)
                ->first();
        }

        if (!$user || !Hash::check($request->password, $user->kata_sandi)) {
            // [🔐 AUTH_SYSTEM] Log failed login attempt
            Log::warning('[🔐 AUTH_SYSTEM] Login gagal', [
                'identifier' => $request->identifier,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Email/Username atau password salah'
            ], 401);
        }

        // [🔐 AUTH_SYSTEM] Update last login timestamp
        $user->update(['terakhir_login_pada' => now()]);

        // [🔐 AUTH_SYSTEM] Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // [🔐 AUTH_SYSTEM] Log successful login (ke log_aktivitas - schema baru)
        $uaInfo = $this->parseUserAgent($request->userAgent());
        LogAktivitas::create([
            'id_pengguna' => $user->id_pengguna,
            'aksi' => 'login',
            'deskripsi' => "User {$user->nama} berhasil login",
            'path' => $request->path(),
            'method' => $request->method(),
            'status_code' => 200,
            'alamat_ip' => $request->ip() ?? '0.0.0.0',
            'user_agent' => $request->userAgent(),
            'device_type' => $uaInfo['device_type'],
            'browser' => $uaInfo['browser'],
            'os' => $uaInfo['os'],
            'waktu_kejadian' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id_pengguna,
                    'name' => $user->nama,
                    'username' => $user->username ?? $user->email,
                    'email' => $user->email,
                    'role' => $user->peran,
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
                'id' => $user->id_pengguna,
                'name' => $user->nama,
                'email' => $user->email,
                'role' => $user->peran,
                'photo' => $user->foto ? url('storage/' . $user->foto) : null,
                'created_at' => $user->dibuat_pada,
            ]
        ]);
    }
}
