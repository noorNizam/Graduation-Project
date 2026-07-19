<?php

namespace App\Console\Commands;

use App\Domain\Repositories\ServingRequestRepositoryInterface;
use App\Domain\Repositories\WalletRepositoryInterface;
use App\Domain\Services\NotificationServiceInterface;
use App\Infrastructure\Models\ServingRequest;
use Illuminate\Console\Command;

class AutoCompleteServingRequests extends Command
{
    protected $signature = 'serving-requests:auto-complete';

    protected $description = 'Auto-complete confirmed and auto-cancel stale accepted serving requests';

    public function __construct(
        private ServingRequestRepositoryInterface $requestRepository,
        private WalletRepositoryInterface $walletRepository,
        private NotificationServiceInterface $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $completed = $this->processAutoCompletions();
        $canceled = $this->processAutoCancels();
        $revisionExpired = $this->processRevisionTimeouts();

        $totalFailed = $completed['failed'] + $canceled['failed'] + $revisionExpired['failed'];

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function processAutoCompletions(): array
    {
        $expired = $this->requestRepository->findExpiredCompletionRequests();
        $ok = 0;
        $fail = 0;

        foreach ($expired as $request) {
            try {
                $this->completeRequest($request);
                $ok++;
            } catch (\Exception $e) {
                $this->error("Failed to auto-complete request #{$request->id}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->info("Auto-completed {$ok} serving requests.");

        return ['ok' => $ok, 'failed' => $fail];
    }

    private function processAutoCancels(): array
    {
        $stale = $this->requestRepository->findStaleAcceptedRequests();
        $ok = 0;
        $fail = 0;

        foreach ($stale as $request) {
            try {
                $this->cancelRequest($request);
                $ok++;
            } catch (\Exception $e) {
                $this->error("Failed to auto-cancel request #{$request->id}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->info("Auto-canceled {$ok} stale accepted requests.");

        return ['ok' => $ok, 'failed' => $fail];
    }

    private function completeRequest(ServingRequest $request): void
    {
        $serving = $request->serving;

        if ($request->held_amount > 0 && $serving) {
            $ownerWallet = $this->walletRepository->findByUserAndUnit(
                $serving->user_id,
                $serving->unit_id
            );

            if ($ownerWallet) {
                $ownerWallet->balance += $request->held_amount;
                $ownerWallet->save();
            }
        }

        $request->held_amount = null;
        $request->held_at = null;
        $request->confirmCompletion();

        if ($serving) {
            $this->notificationService->send(
                $request->requester_id,
                'auto_completed',
                'تم إتمام الخدمة تلقائياً',
                "تم إتمام خدمة {$serving->title} تلقائياً لعدم ردك",
                [
                    'serving_id' => $serving->id,
                    'request_id' => $request->id,
                ]
            );
        }
    }

    private function processRevisionTimeouts(): array
    {
        $stale = $this->requestRepository->findExpiredRevisionRequests();
        $ok = 0;
        $fail = 0;

        foreach ($stale as $request) {
            try {
                $this->cancelRequest($request);
                $ok++;
            } catch (\Exception $e) {
                $this->error("Failed to auto-cancel revision request #{$request->id}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->info("Auto-canceled {$ok} revision-expired requests.");

        return ['ok' => $ok, 'failed' => $fail];
    }

    private function cancelRequest(ServingRequest $request): void
    {
        $serving = $request->serving;

        if ($request->held_amount > 0 && $serving) {
            $wallet = $this->walletRepository->findByUserAndUnit(
                $request->requester_id,
                $serving->unit_id
            );

            if ($wallet) {
                $wallet->balance += $request->held_amount;
                $wallet->save();
            }
        }

        $request->held_amount = null;
        $request->held_at = null;
        $request->cancel();

        if ($serving) {
            $this->notificationService->send(
                $request->requester_id,
                'auto_canceled',
                'تم إلغاء الطلب تلقائياً',
                $request->revision_count > 0
                    ? "تم إلغاء طلب خدمة {$serving->title} لعدم إعادة الإرسال من مقدم الخدمة"
                    : "تم إلغاء طلب خدمة {$serving->title} لعدم الرد",
                [
                    'serving_id' => $serving->id,
                    'request_id' => $request->id,
                ]
            );

            if ($request->revision_count > 0) {
                $this->notificationService->send(
                    $serving->user_id,
                    'auto_canceled',
                    'تم إلغاء الطلب تلقائياً',
                    "تم إلغاء طلب خدمة {$serving->title} بسبب عدم إعادة الإرسال بعد طلب التعديل",
                    [
                        'serving_id' => $serving->id,
                        'request_id' => $request->id,
                    ]
                );
            }
        }
    }
}
