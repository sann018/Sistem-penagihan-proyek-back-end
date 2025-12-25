<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * [🔐 PERMISSION_SYSTEM] Handle permission checking untuk protected routes
     * Validasi role user terhadap required roles (super_admin, admin, viewer)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Role yang diizinkan (comma-separated: super_admin,admin,viewer)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // [\ud83d\udd10 PERMISSION_SYSTEM] Support kolom role accessor dan peran column
        $userRole = $user->role ?? $user->peran;
        
        // [\ud83d\udd10 PERMISSION_SYSTEM] Debug logging untuk troubleshooting permission issues
        Log::info('[\ud83d\udd10 PERMISSION_SYSTEM] Role check', [
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $user->id,
            'user_role' => $userRole,
            'required_roles' => $roles,
            'has_access' => in_array($userRole, $roles)
        ]);
        
        if (!in_array($userRole, $roles)) {
            // [\ud83d\udd10 PERMISSION_SYSTEM] Log akses terlarang untuk audit trail
            Log::warning('[\ud83d\udd10 PERMISSION_SYSTEM] Akses ditolak - role tidak sesuai', [
                'endpoint' => $request->path(),
                'user_id' => $user->id,
                'user_role' => $userRole,
                'required_roles' => $roles
            ]);
            
            return response()->json([
                'message' => '[\ud83d\udd10 PERMISSION_SYSTEM] Akses ditolak - Anda tidak memiliki permission untuk aksi ini',
                'required_roles' => $roles,
                'your_role' => $userRole
            ], 403);
        }

        return $next($request);
    }
}
