<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingProposalServiceInterface;
use App\Traits\Loggable;
use AwaisJameel\DiditLaravelClient\Exceptions\DiditException;
use AwaisJameel\DiditLaravelClient\Facades\DiditLaravelClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TroubleshootingController
{
    use Loggable;

    public function __construct(
        private ServingProposalServiceInterface $proposalService
    ) {}

    public function testMonitor()
    {
        return $this->executeWithLogging(__METHOD__, function () {
            // Simulate some processing time
            usleep(500000); // 0.5 second delay

            // Simulate some business logic
            $data = [
                'message' => 'Test completed successfully',
                'timestamp' => now()->toISOString(),
                'processed_items' => rand(10, 100),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'execution_note' => 'This endpoint tests AOP logging and monitoring',
            ]);
        });
    }

    public function testIndex()
    {
        return $this->executeWithLogging(__METHOD__, function () {
            return response()->json($this->proposalService->indexStatus());
        });
    }

    public function testError()
    {
        return $this->executeWithLogging(__METHOD__, function () {
            // 0.3 second delay
            usleep(300000);

            throw new \Exception('This is a simulated error to test error logging in AOP');
        });
    }

    public function testDidit(Request $request)
    {
        return $this->executeWithLogging(__METHOD__, function () use ($request) {
            $dryRun = $request->boolean('dry');

            $config = [
                'api_key_set' => (bool) config('didit-laravel-client.api_key'),
                'client_id_set' => (bool) config('didit-laravel-client.client_id'),
                'workflow_id_set' => (bool) config('didit-laravel-client.workflow_id'),
                'webhook_secret_set' => (bool) config('didit-laravel-client.webhook_secret'),
                'base_url' => config('didit-laravel-client.base_url'),
                'auth_url' => config('didit-laravel-client.auth_url'),
                'api_version' => config('didit-laravel-client.api_version'),
                'timeout' => config('didit-laravel-client.timeout'),
                'debug' => (bool) config('didit-laravel-client.debug'),
            ];

            $sessionCreate = null;
            $sessionReadback = null;

            if (! $dryRun) {
                $vendorToken = 'diag-'.Str::random(24);
                $startedAt = microtime(true);

                try {
                    $session = DiditLaravelClient::createSession(
                        callbackUrl: route('identity.callback'),
                        vendorData: $vendorToken,
                        options: ['workflow_id' => config('didit-laravel-client.workflow_id')]
                    );

                    $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $verificationUrl = $session['url'] ?? $session['url_session'] ?? $session['verification_url'] ?? null;
                    $sessionId = $session['session_id'] ?? $session['id'] ?? null;

                    $sessionCreate = [
                        'success' => (bool) ($verificationUrl && $sessionId),
                        'session_id' => $sessionId,
                        'verification_url' => $verificationUrl,
                        'latency_ms' => $latencyMs,
                    ];

                    if ($sessionId) {
                        try {
                            $decision = DiditLaravelClient::getSession($sessionId);
                            $sessionReadback = [
                                'success' => true,
                                'decision_status' => $decision['status'] ?? $decision['decision']['status'] ?? null,
                            ];
                        } catch (DiditException $e) {
                            $sessionReadback = [
                                'success' => false,
                                'message' => $e->getMessage(),
                            ];
                        }
                    }
                } catch (DiditException $e) {
                    Log::error('Didit diagnostic: session creation failed', ['message' => $e->getMessage()]);
                    $sessionCreate = [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $signatureTest = $this->webhookSignatureSelfTest();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'dry_run' => $dryRun,
                    'config' => $config,
                    'session_create' => $sessionCreate,
                    'session_readback' => $sessionReadback,
                    'webhook_signature_self_test' => $signatureTest,
                ],
            ]);
        });
    }

    private function webhookSignatureSelfTest(): array
    {
        $secret = config('didit-laravel-client.webhook_secret');

        if (! $secret) {
            return [
                'success' => false,
                'message' => 'DIDIT_WEBHOOK_SECRET is not set; signature verification cannot be tested',
            ];
        }

        $payload = [
            'session_id' => 'diag-'.Str::random(8),
            'status' => 'Approved',
            'vendor_data' => 'diag-token',
            'timestamp' => time(),
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $secret);

        try {
            $verified = DiditLaravelClient::verifyWebhookSignature(
                [
                    'x-signature' => $signature,
                    'x-timestamp' => (string) $payload['timestamp'],
                ],
                $rawBody
            );

            return [
                'success' => true,
                'message' => 'Configured secret verified a locally-signed payload',
            ];
        } catch (DiditException $e) {
            Log::warning('Didit diagnostic: webhook signature self-test failed', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
