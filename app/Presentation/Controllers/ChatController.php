<?php

namespace App\Presentation\Controllers;

use App\Domain\Services\ChatServiceInterface;
use App\Presentation\Requests\AddMembersRequest;
use App\Presentation\Requests\CreateChatRequest;
use App\Presentation\Requests\SendMessageRequest;
use App\Presentation\Requests\UpdateGroupRequest;

class ChatController
{
    public function __construct(
        private ChatServiceInterface $chatService
    ) {}

    public function createChat(CreateChatRequest $request)
    {
        $result = $this->chatService->createChat(
            auth()->id(),
            $request->validated()
        );

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function sendMessage(int $chatId, SendMessageRequest $request)
    {
        $result = $this->chatService->sendMessage(
            $chatId,
            auth()->id(),
            $request->validated()['content']
        );

        return response()->json($result, $result['success'] ? 201 : 500);
    }

    public function getChats()
    {
        $result = $this->chatService->getChats(auth()->id());
        return response()->json($result, 200);
    }

    public function getMessages(int $chatId)
    {
        $afterId = request()->integer('after');
        $beforeId = request()->integer('before');
        $result = $this->chatService->getMessages(
            $chatId,
            auth()->id(),
            $afterId ?: null,
            $beforeId ?: null
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function markAsRead(int $chatId)
    {
        $result = $this->chatService->markAsRead($chatId, auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function getMembers(int $chatId)
    {
        $result = $this->chatService->getMembers($chatId, auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function addMembers(int $chatId, AddMembersRequest $request)
    {
        $result = $this->chatService->addMembers(
            $chatId,
            auth()->id(),
            $request->validated()['user_ids']
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function removeMember(int $chatId, int $userId)
    {
        $result = $this->chatService->removeMember(
            $chatId,
            auth()->id(),
            $userId
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function leaveGroup(int $chatId)
    {
        $result = $this->chatService->leaveGroup($chatId, auth()->id());

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function updateGroup(int $chatId, UpdateGroupRequest $request)
    {
        $result = $this->chatService->updateGroup(
            $chatId,
            auth()->id(),
            $request->validated()
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }
}
