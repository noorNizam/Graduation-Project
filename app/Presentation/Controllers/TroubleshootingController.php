<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ServingProposalServiceInterface;
use App\Traits\Loggable;

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
}
