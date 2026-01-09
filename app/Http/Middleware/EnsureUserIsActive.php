<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Block access for deactivated accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (($user->aktif ?? true) === false)) {
            // Defensive: revoke the token being used (in case the account was deactivated
            // outside the standard endpoint, or tokens weren't revoked for some reason).
            $request->user()?->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun dinonaktifkan. Hubungi Super Admin.',
            ], 403);
        }

        return $next($request);
    }
}
