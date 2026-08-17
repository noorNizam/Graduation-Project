<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\IdentityVerificationServiceInterface;
use AwaisJameel\DiditLaravelClient\Exceptions\DiditException;
use AwaisJameel\DiditLaravelClient\Exceptions\WebhookVerificationException;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IdentityVerificationController
{
    public function __construct(
        private IdentityVerificationServiceInterface $service
    ) {}

    public function start()
    {
        $userId = auth()->id();
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $result = $this->service->startVerification($userId);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function status()
    {
        $userId = auth()->id();
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
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
        try {
            $payload = DiditLaravelClient::processWebhook($request);
        } catch (WebhookVerificationException $e) {
            Log::warning('Didit webhook signature verification failed', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
            ], 401);
        } catch (DiditException $e) {
            Log::error('Didit webhook verification error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook verification error',
            ], 400);
        }

        $result = $this->service->handleCallback($payload);

        if (! ($result['success'] ?? false)) {
            Log::warning('Didit webhook could not be processed', $result);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Processing failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received and processed.',
        ], 200);
    }
}
