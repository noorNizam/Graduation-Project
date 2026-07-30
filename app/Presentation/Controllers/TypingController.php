<?php

namespace App\Presentation\Controllers;

use App\Domain\Repositories\ChatRepositoryInterface;
use App\Events\UserTypingEvent;

class TypingController
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository
    ) {}

    public function typing(int $chatId)
    {
        if (! $this->chatRepository->isMember($chatId, auth()->id())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $user = auth()->user();

        broadcast(new UserTypingEvent(
            $chatId,
            $user->id,
            $user->full_name,
            true,
            $chat->type
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    public function stopTyping(int $chatId)
    {
        if (! $this->chatRepository->isMember($chatId, auth()->id())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $chat = $this->chatRepository->findById($chatId);

        if (! $chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $user = auth()->user();

        broadcast(new UserTypingEvent(
            $chatId,
            $user->id,
            $user->full_name,
            false,
            $chat->type
        ))->toOthers();

        return response()->json(['success' => true]);
    }
}
