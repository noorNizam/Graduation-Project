<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\UserManagementServiceInterface;
use App\Presentation\Requests\BlockUserRequest;
use App\Presentation\Requests\GetUserDetailsRequest;
use App\Presentation\Requests\SearchUsersRequest;
use App\Presentation\Requests\UnblockUserRequest;
use App\Presentation\Requests\UpdateProfileRequest;

class UserManagementController
{
    public function __construct(
        private UserManagementServiceInterface $userManagementService
    ) {}

    public function updateProfile(UpdateProfileRequest $request)
    {
        $data = $request->validated();

        $profilePicture = $request->file('profile_picture');

        $result = $this->userManagementService->updateProfile(
            auth()->id(),
            $data,
            $profilePicture
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getUserById(int $id, GetUserDetailsRequest $request)
    {
        $result = $this->userManagementService->getUserById($id);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function searchUsers(SearchUsersRequest $request)
    {
        $result = $this->userManagementService->searchUsers(
            $request->validated()
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function blockUser(int $id, BlockUserRequest $request)
    {
        $result = $this->userManagementService->blockUser($id);

        $status = 200;
        if (! $result['success']) {
            $status = isset($result['message']) && $result['message'] === 'Cannot block yourself' ? 403 : 404;
        }

        return response()->json($result, $status);
    }

    public function unblockUser(int $id, UnblockUserRequest $request)
    {
        $result = $this->userManagementService->unblockUser($id);

        $status = 200;
        if (! $result['success']) {
            $status = isset($result['message']) && $result['message'] === 'Cannot unblock yourself' ? 403 : 404;
        }

        return response()->json($result, $status);
    }
}
