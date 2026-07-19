<?php

namespace App\Application\Services;

use App\Domain\Services\NotificationServiceInterface;
use App\Domain\Services\UserManagementServiceInterface;
use App\Infrastructure\Models\User;
use App\Traits\HandlesDatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserManagementService implements UserManagementServiceInterface
{
    use HandlesDatabaseTransactions;

    public function __construct(
        private NotificationServiceInterface $notificationService
    ) {}

    private const ALLOWED_PROFILE_FIELDS = [
        'full_name',
        'current_job',
        'address',
        'gender',
        'phone_number',
        'birth_date',
    ];

    public function updateProfile(int $userId, array $data, $profilePicture = null): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($user, $data, $profilePicture, $userId) {
            $updateData = [];

            foreach (self::ALLOWED_PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if ($profilePicture) {
                $path = sprintf('profiles/%s/%s', $userId, date('Y/m/d'));
                $filename = sprintf('%s_%s.%s', time(), Str::random(8), $profilePicture->getClientOriginalExtension());

                $stored = $profilePicture->storeAs($path, $filename, 'public');

                $updateData['profile_picture'] = Storage::url($stored);
            }

            if (! empty($updateData)) {
                $user->update($updateData);
            }

            return $user->fresh();
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        return [
            'success' => true,
            'data' => $this->formatUser($transactionResult['data']),
            'message' => 'Profile updated successfully',
        ];
    }

    public function getUserById(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        return [
            'success' => true,
            'data' => $this->formatUser($user),
        ];
    }

    public function searchUsers(array $params): array
    {
        $query = User::query();

        if (! empty($params['name'])) {
            $query->where('full_name', 'LIKE', '%'.$params['name'].'%');
        }

        if (! empty($params['email'])) {
            $query->where('email', 'LIKE', '%'.$params['email'].'%');
        }

        if (! empty($params['gender'])) {
            $query->where('gender', $params['gender']);
        }

        if (! empty($params['current_job'])) {
            $query->where('current_job', 'LIKE', '%'.$params['current_job'].'%');
        }

        if (isset($params['skip'])) {
            $query->skip((int) $params['skip']);
        }

        if (isset($params['take'])) {
            $query->take((int) $params['take']);
        }

        $users = $query->get();

        return [
            'success' => true,
            'data' => $users->map(fn ($user) => $this->formatUser($user)),
        ];
    }

    public function blockUser(int $userId): array
    {
        if ($userId === auth()->id()) {
            return [
                'success' => false,
                'message' => 'Cannot block yourself',
            ];
        }

        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($user) {
            $user->update(['is_active' => false]);

            return $user->fresh();
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $this->notificationService->send(
            $userId,
            'account_blocked',
            'تم حظر حسابك',
            'تم حظر حسابك من قبل الإدارة',
            ['user_id' => $userId]
        );

        return [
            'success' => true,
            'data' => $this->formatUser($transactionResult['data']),
            'message' => 'User blocked successfully',
        ];
    }

    public function unblockUser(int $userId): array
    {
        if ($userId === auth()->id()) {
            return [
                'success' => false,
                'message' => 'Cannot unblock yourself',
            ];
        }

        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $transactionResult = $this->executeWithTransaction(function () use ($user) {
            $user->update(['is_active' => true]);

            return $user->fresh();
        });

        if (! $transactionResult['success']) {
            return $transactionResult;
        }

        $this->notificationService->send(
            $userId,
            'account_unblocked',
            'تم إلغاء حظر حسابك',
            'تم إلغاء حظر حسابك من قبل الإدارة',
            ['user_id' => $userId]
        );

        return [
            'success' => true,
            'data' => $this->formatUser($transactionResult['data']),
            'message' => 'User unblocked successfully',
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'current_job' => $user->current_job,
            'address' => $user->address,
            'gender' => $user->gender,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'profile_picture' => $user->profile_picture ? asset($user->profile_picture) : null,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
