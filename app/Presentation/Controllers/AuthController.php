<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Infrastructure\Models\RefreshToken;
use App\Presentation\Requests\LoginRequest;
use App\Presentation\Requests\RefreshTokenRequest;
use App\Presentation\Requests\RegisterUserRequest;
use App\Presentation\Requests\SendOtpRequest;
use Illuminate\Support\Str;

class AuthController
{
    public function __construct(
        private EmailVerificationServiceInterface $emailVerificationService,
        private UserRegistrationServiceInterface $userRegistrationService
    ) {}

    public function sendVerificationOtp(SendOtpRequest $request)
    {
        $result = $this->emailVerificationService->sendVerificationOtp(
            $request->validated()['email']
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function registerCustomer(RegisterUserRequest $request)
    {
        $validated = $request->validated();
        $profilePicture = $request->file('profile_picture');

        $result = $this->userRegistrationService->registerCustomer(
            [
                'full_name' => $validated['full_name'],
                'current_job' => $validated['current_job'] ?? null,
                'address' => $validated['address'] ?? null,
                'gender' => $validated['gender'] ?? 'not specified',
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ],
            $validated['otp'],
            $profilePicture
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Attempt authentication
        if (! auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $user = auth()->user();

        // Prevent blocked users from logging in
        if (! $user->is_active) {
            auth()->logout();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Contact support.',
            ], 403);
        }

        // Create access token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Create refresh token
        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'refresh_token' => $refreshToken->token,
            'expires_in' => 86400,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'role' => $user->role,
            ],
        ]);
    }

    public function refresh(RefreshTokenRequest $request)
    {
        $refreshToken = RefreshToken::where('token', $request->validated()['refresh_token'])
            ->whereNull('revoked_at')
            ->first();

        if (! $refreshToken || ! $refreshToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        $user = $refreshToken->user;

        // Revoke old refresh token (rotation)
        $refreshToken->revoke();

        // Issue new pair
        $newToken = $user->createToken('auth-token')->plainTextToken;

        $newRefreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'success' => true,
            'token' => $newToken,
            'refresh_token' => $newRefreshToken->token,
            'expires_in' => 86400,
        ]);
    }
}
