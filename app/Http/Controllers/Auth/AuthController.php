<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        $token = JWTAuth::fromUser($user);
        $refreshToken = JWTAuth::customClaims(['type' => 'refresh'])->fromUser($user);

        // Determine redirect URL based on role
        $redirectUrl = '/dashboard';
        if ($user->role === 'teacher') {
            $redirectUrl = '/dashboard/teacher';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60, // in seconds
                'refresh_token' => $refreshToken,
                'redirect_url' => $redirectUrl,
            ],
        ]);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        
        // Load permissions and roles
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'whatsapp' => $user->whatsapp,
            'timezone' => $user->timezone,
            'status' => $user->status,
            'permissions' => $permissions,
            'roles' => $roles,
        ];

        // If user is a teacher, include teacher profile and meet_link
        if ($user->role === 'teacher' && $user->teacher) {
            $userData['teacher'] = [
                'id' => $user->teacher->id,
                'meet_link' => $user->teacher->meet_link,
                'hourly_rate' => $user->teacher->hourly_rate,
                'currency' => $user->teacher->currency,
                'timezone' => $user->teacher->timezone,
                'status' => $user->teacher->status,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $userData,
            ],
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get available roles for login.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => ucfirst($role->name),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'roles' => $roles,
            ],
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $refreshToken = $request->input('refresh_token') ?? $request->bearerToken();
            
            if (!$refreshToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Refresh token is required.',
                ], 400);
            }

            // Set the token and get the user
            JWTAuth::setToken($refreshToken);
            $user = JWTAuth::authenticate();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token.',
                ], 401);
            }

            // Generate new access token
            $newToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'access_token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60, // in seconds
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not refresh token.',
            ], 401);
        }
    }
}
