<?php

namespace App\Presentation\Controllers;

use App\Presentation\Requests\SendOtpRequest;
use App\Presentation\Requests\RegisterUserRequest;
use App\Domain\Services\EmailVerificationServiceInterface;
use App\Domain\Services\UserRegistrationServiceInterface;
use App\Presentation\Requests\LoginRequest;



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
            $validated['otp']
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
        if (!auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $user = auth()->user();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'role' => $user->role
            ],
        ]);
    }
}
