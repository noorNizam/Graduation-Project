<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\ServingRequestServiceInterface;
use App\Events\ComplaintResolved;
use App\Presentation\Requests\ComplaintFilterRequest;
use App\Presentation\Requests\UpdateComplaintStatusRequest;

class AdminComplaintController
{
    public function __construct(
        private ComplaintServiceInterface $complaintService,
        private ServingRequestServiceInterface $servingRequestService
    ) {}

    public function index(ComplaintFilterRequest $request)
    {
        $filters = $request->validated();
        $perPage = $filters['per_page'] ?? 15;

        $result = $this->complaintService->getAllComplaints($filters, $perPage);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function show(int $id)
    {
        $result = $this->complaintService->getComplaint($id);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function updateStatus(int $id, UpdateComplaintStatusRequest $request)
    {
        $validated = $request->validated();
        $outcome = $validated['outcome'] ?? null;

        $complaintModel = $this->complaintService->getComplaintModel($id);
        // Resolutions are one-way: re-sending status=resolved on an already
        // resolved complaint must not re-fire penalties or escalation.
        $wasAlreadyResolved = $complaintModel?->status === 'resolved';

        // The outcome is the single verdict: justified refunds the requester,
        // unjustified releases the owner. Settle the escrow BEFORE finalizing
        // the resolution so a settlement failure leaves the complaint
        // untouched instead of resolved-with-stuck-funds. Only genuinely
        // disputed requests carry held money; anything else resolves as a
        // plain complaint with no funds movement.
        if ($validated['status'] === 'resolved' && $outcome !== null) {
            if ($complaintModel?->serving_request_id && $this->servingRequestService->isRequestDisputed($complaintModel->serving_request_id)) {
                $disputeResult = $this->servingRequestService->resolveDispute(
                    $complaintModel->serving_request_id,
                    $outcome === 'justified' ? 'refund_to_requester' : 'release_to_owner',
                    $complaintModel->id
                );

                if (! ($disputeResult['success'] ?? false)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dispute resolution failed: '.($disputeResult['message'] ?? 'Unknown error'),
                    ], 500);
                }
            }
        }

        $result = $this->complaintService->updateComplaintStatus(
            $id,
            $validated['status'],
            $validated['admin_note'] ?? null,
            $validated['documents_requested_from'] ?? null,
            $validated['documents_due_at'] ?? null,
            $outcome,
            auth()->id()
        );

        if (! $result['success']) {
            return response()->json($result, 500);
        }

        if ($validated['status'] === 'resolved' && ! $wasAlreadyResolved) {
            // Re-fetch so the listener sees the persisted outcome.
            $complaintModel = $this->complaintService->getComplaintModel($id);
            if ($complaintModel) {
                event(new ComplaintResolved($complaintModel, $validated['admin_note'] ?? ''));
            }
        }

        return response()->json($result, 200);
    }

    public function destroy(int $id)
    {
        $result = $this->complaintService->deleteComplaint($id);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function statistics()
    {
        $result = $this->complaintService->getStatistics();

        return response()->json($result, 200);
    }
}
