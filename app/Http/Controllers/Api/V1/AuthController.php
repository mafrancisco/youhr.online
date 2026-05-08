<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a personal access token.
     *
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username'    => ['required', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'username' => $user->username,
                'fullname' => $user->fullname,
                'email'    => $user->email,
                'type'     => $user->type,
                'role'     => $user->isHR() ? 'hr' : 'employee',
            ],
        ]);
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
