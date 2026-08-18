<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ComplaintServiceInterface;
use App\Domain\Services\PenaltyServiceInterface;
use App\Infrastructure\Models\ComplaintModel;
use App\Presentation\Requests\StoreComplaintRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserComplaintController
{
    public function __construct(
        private ComplaintServiceInterface $complaintService
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

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        $complaint = $result['data'];
        if ($complaint->complainant_id !== auth()->id() && $complaint->accused_user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this complaint',
            ], 403);
        }

        return response()->json($result, 200);
    }

    /**
     * رفع وثائق إضافية (للمشتكي أو المشتكى عليه)
     */
    public function uploadDocuments(Request $request, int $id)
    {
        $complaint = ComplaintModel::find($id);
        if (!$complaint) {
            return response()->json(['success' => false, 'message' => 'Complaint not found'], 404);
        }

        // التحقق من أن المستخدم طرف في الشكوى
        if ($complaint->complainant_id !== auth()->id() && $complaint->accused_user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // التحقق من الحالة
        if (!$complaint->isAwaitingDocuments()) {
            return response()->json(['success' => false, 'message' => 'Complaint is not awaiting documents'], 422);
        }

        $request->validate([
            'documents' => 'required|array',
            'documents.*' => 'file|max:5120', // 5MB لكل ملف
        ]);

        // حفظ الوثائق
        $paths = [];
        foreach ($request->file('documents') as $file) {
            $path = $file->store('complaints/documents/' . $complaint->id, 'public');
            $paths[] = $path;
        }

        // تحديث حالة رفع الوثائق حسب المستخدم
        if (auth()->id() === $complaint->complainant_id) {
            $complaint->complainant_documents_uploaded = true;
        } elseif (auth()->id() === $complaint->accused_user_id) {
            $complaint->accused_documents_uploaded = true;
        }
        $complaint->save();

        // التحقق من الوضع بعد رفع الوثائق
        $result = $this->complaintService->checkDocumentStatusAndApplyDecision($complaint);

        return response()->json([
            'success' => true,
            'message' => 'Documents uploaded successfully',
            'data' => [
                'paths' => $paths,
                'decision' => $result
            ]
        ]);
    }

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