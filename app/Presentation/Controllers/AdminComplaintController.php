<?php

namespace App\Presentation\Controllers;

use App\Application\Services\ComplaintService;
use App\Events\ComplaintResolved;
use App\Presentation\Requests\ComplaintFilterRequest;
use App\Presentation\Requests\UpdateComplaintStatusRequest;

class AdminComplaintController
{
    public function __construct(
        private ComplaintService $complaintService
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

        $result = $this->complaintService->updateComplaintStatus(
            $id,
            $validated['status'],
            $validated['admin_note'] ?? null
        );

        if ($validated['status'] === 'resolved') {
            $complaintModel = $this->complaintService->getComplaintModel($id);
            if ($complaintModel) {
                event(new ComplaintResolved($complaintModel, $validated['admin_note'] ?? ''));
            }
        }

        return response()->json($result, $result['success'] ? 200 : 500);
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
