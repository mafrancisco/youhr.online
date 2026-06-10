<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Issue a personal access token.
     *
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Password-based login is disabled. Use Google OAuth via the web onboarding flow.',
        ], 403);
    }

    /**
     * Revoke the current token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Get the authenticated user's profile.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'email'    => $user->email,
            'type'     => $user->type,
            'role'     => $user->isHR() ? 'hr' : 'employee',
        ]);
    }
}
