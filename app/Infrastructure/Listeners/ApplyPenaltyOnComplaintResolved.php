<?php

namespace App\Infrastructure\Listeners;

use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Events\ComplaintResolved;
use App\Infrastructure\Models\ComplaintModel;
use App\Infrastructure\Models\User;

class ApplyPenaltyOnComplaintResolved
{
    public function __construct(
        private PenaltyServiceInterface $penaltyService,
        private NotificationServiceInterface $notificationService,
    ) {}

    public function handle(ComplaintResolved $event): void
    {
        $complaint = $event->complaint;
        $accusedUserId = $complaint->accused_user_id;
        $accusedUser = User::find($accusedUserId);
        $resolvedComplaintsCount = ComplaintModel::where('accused_user_id', $accusedUserId)
            ->where('status', 'resolved')
            ->count();

        $hoursToDeduct = $resolvedComplaintsCount * 1;

        if ($hoursToDeduct > 0) {
            $this->penaltyService->deductHours(
                $accusedUserId,
                $hoursToDeduct,
                $complaint->id,
                "Deducted {$hoursToDeduct} hours due to {$resolvedComplaintsCount} resolved complaints against user"
            );

            $this->notificationService->send(
                $accusedUserId,
                'penalty_applied',
                'Penalty has been applied',
                "{$hoursToDeduct} hours have been deducted because of a resolved complaint ",
                ['complaint_id' => $complaint->id]
            );
        }

        if ($resolvedComplaintsCount >= 5) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'ban',
                $complaint->id,
                'Permanent ban due to repeated complaints (5 resolved complaints)'
            );
        } elseif ($resolvedComplaintsCount >= 3) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'suspend',
                $complaint->id,
                '7-day suspension due to repeated complaints (3 resolved complaints)'
            );
        } elseif ($resolvedComplaintsCount >= 2) {
            $this->penaltyService->applyPenalty(
                $accusedUserId,
                'warning',
                $complaint->id,
                'Warning due to repeated complaints (2 resolved complaints)'
            );
        }
        $this->notificationService->send(
            $complaint->complainant_id,
            'complaint_resolved',
            'Complaint resolved',
            "Your complaint against {$accusedUser->full_name} has been resolved",
            ['complaint_id' => $complaint->id]
        );
    }
}
