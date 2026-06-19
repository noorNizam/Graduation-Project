<?php

namespace App\Presentation\Controllers;

use App\Application\Services\ComplaintService;
use App\Presentation\Requests\StoreComplaintRequest;
use App\Domain\Services\PenaltyServiceInterface; 

class UserComplaintController
{
    public function __construct(
        private ComplaintService $complaintService
    ) {}

    public function store(StoreComplaintRequest $request)
    {
        $data = $request->validated();
        $data['complainant_id'] = auth()->id();

        $attachment = $request->file('attachment');
        $result = $this->complaintService->createComplaint($data, $attachment);

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function index()
    {
        $result = $this->complaintService->getUserComplaints(auth()->id(), 15);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function show(int $id)
    {
        $result = $this->complaintService->getComplaint($id);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        $complaint = $result['data'];
        if ($complaint->complainant_id !== auth()->id() && $complaint->accused_user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بمشاهدة هذه الشكوى'
            ], 403);
        }

        return response()->json($result, 200);
    }

    // 🔥 دالة عرض المحفظة
    public function getWalletBalance()
    {
        $result = app(PenaltyServiceInterface::class)->getWalletBalance(auth()->id());
        
        return response()->json($result, $result['success'] ? 200 : 404);
    }
    public function myPenalties()
{
    $result = app(PenaltyServiceInterface::class)->getUserPenalties(auth()->id(), 15);
    
    return response()->json($result, $result['success'] ? 200 : 500);
}
}