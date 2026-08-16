<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\IdentityVerificationServiceInterface;
use Illuminate\Http\Request;

class IdentityVerificationController
{
    public function __construct(
        private IdentityVerificationServiceInterface $service
    ) {}

    public function start()
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $result = $this->service->startVerification($userId);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function status()
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $result = $this->service->getStatus($userId);
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    public function callback(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Identity verification process completed. Waiting for final approval.',
        ], 200);
    }
    public function webhook(Request $request)
{
    \Log::info('Didit Webhook Received Raw Data:', $request->all());

    try {

        $this->service->handleCallback($request->all());
    } catch (\Throwable $e) {
      
        \Log::error('Didit Webhook Service Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    return response()->json([
        'success' => true,
        'message' => 'Webhook received and processed.'
    ], 200);
}
}