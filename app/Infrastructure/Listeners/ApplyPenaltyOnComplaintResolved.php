<?php

namespace App\Infrastructure\Listeners;

use App\Events\ComplaintResolved;
use App\Domain\Services\PenaltyServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use Illuminate\Support\Facades\Log;

class ApplyPenaltyOnComplaintResolved
{
    public function __construct(
        private PenaltyServiceInterface $penaltyService
    ) {}

    public function handle(ComplaintResolved $event): void
    {
        Log::info('Listener is working!', ['complaint_id' => $event->complaint->id]);

        $complaint = $event->complaint;
        $accusedUserId = $complaint->accused_user_id;

        // 🔥 تأكدي من رقم المستخدم
        Log::info('Accused user ID', ['user_id' => $accusedUserId]);

        // عدد الشكاوى المنحلة بحق المستخدم
        $resolvedComplaintsCount = ComplaintModel::where('accused_user_id', $accusedUserId)
            ->where('status', 'resolved')
            ->count();

        Log::info('Resolved complaints count', ['count' => $resolvedComplaintsCount]);

        // 🔥 تأكدي من الرقم
        $hoursToDeduct = $resolvedComplaintsCount * 1; // خصم 1 ساعة لكل شكوى

        Log::info('Hours to deduct', ['hours' => $hoursToDeduct]);

        // 🔥 جربي تخصمي حتى لو كانت 0
        if ($hoursToDeduct > 0) {
            $result = $this->penaltyService->deductHours(
                $accusedUserId,
                $hoursToDeduct,
                $complaint->id,
                "خصم {$hoursToDeduct} ساعة بسبب {$resolvedComplaintsCount} شكوى منحلة بحق المستخدم"
            );

            Log::info('Deduct hours result', ['result' => $result]);
        } else {
            Log::info('No hours to deduct, skipping deduction');
        }

        // عقوبات إضافية
        if ($resolvedComplaintsCount >= 5) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'ban',
                $complaint->id,
                'حظر دائم بسبب تكرر الشكاوى (5 شكاوى منحلة)'
            );
        } elseif ($resolvedComplaintsCount >= 3) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'suspend',
                $complaint->id,
                'تعليق 7 أيام بسبب تكرر الشكاوى (3 شكاوى منحلة)'
            );
        } elseif ($resolvedComplaintsCount >= 2) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'warning',
                $complaint->id,
                'تحذير بسبب تكرار الشكاوى (شكويان منحلتان)'
            );
        }
    }
}