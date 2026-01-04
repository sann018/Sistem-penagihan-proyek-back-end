<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset link via email.
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // User table di sistem ini adalah `pengguna`
            'email' => 'required|email|exists:pengguna,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan',
                'errors' => $validator->errors()
            ], 422);
        }

        // Send password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Link reset password telah dikirim ke email Anda. Silakan cek inbox atau spam folder.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim email reset password. Silakan coba lagi.'
        ], 500);
    }
}
