<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\ApiResource;
use App\Http\Resources\Api\MessageResource;
use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageController extends ApiResourceController
{
    protected string $modelClass = Message::class;

    protected string $resourceClass = MessageResource::class;

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'word' => ['required', 'string'],
        ]);

        $messages = Message::query()
            ->where(function ($query) use ($validated): void {
                $query->where('user_id', $validated['user_id'])
                    ->orWhere('addressee_user_id', $validated['user_id']);
            })
            ->where('message_content', 'like', "%{$validated['word']}%")
            ->with(['user', 'addresseeUser', 'addresseeGroup', 'files'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(MessageResource::collection($messages), $this->apiMessage('find_all_success'), $messages->lastPage(), $messages->total());
    }

    public function conversations(Request $request): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $messages = Message::query()
            ->where('user_id', $validated['user_id'])
            ->orWhere('addressee_user_id', $validated['user_id'])
            ->with(['user', 'addresseeUser', 'addresseeGroup'])
            ->latest('id')
            ->get();

        $conversations = $messages
            ->groupBy(fn (Message $message): string => $message->addressee_group_id !== null
                ? 'group:'.$message->addressee_group_id
                : 'user:'.($message->user_id === $validated['user_id'] ? $message->addressee_user_id : $message->user_id))
            ->map(fn ($items) => [
                'conversation_key' => $items->first()->addressee_group_id !== null
                    ? 'group:'.$items->first()->addressee_group_id
                    : 'user:'.($items->first()->user_id === $validated['user_id'] ? $items->first()->addressee_user_id : $items->first()->user_id),
                'last_message' => MessageResource::make($items->first()),
                'unread_count' => $items->where('addressee_user_id', $validated['user_id'])->where('status', 'unread')->count(),
            ])
            ->values();

        return $this->handleResponse($conversations, $this->apiMessage('find_all_success'));
    }

    public function userConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'addressee_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $messages = Message::query()
            ->where(function ($query) use ($validated): void {
                $query->where('user_id', $validated['user_id'])
                    ->where('addressee_user_id', $validated['addressee_user_id']);
            })
            ->orWhere(function ($query) use ($validated): void {
                $query->where('user_id', $validated['addressee_user_id'])
                    ->where('addressee_user_id', $validated['user_id']);
            })
            ->with(['user', 'addresseeUser', 'files'])
            ->oldest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse(MessageResource::collection($messages), $this->apiMessage('find_all_success'), $messages->lastPage(), $messages->total());
    }

    public function groupConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
        ]);

        $group = Group::query()->with('users')->findOrFail($validated['group_id']);
        $messages = Message::query()
            ->where('addressee_group_id', $group->id)
            ->with(['user', 'addresseeGroup', 'files'])
            ->oldest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->handleResponse([
            'group' => ApiResource::make($group),
            'is_member' => $group->users->contains('id', $validated['user_id']),
            'messages' => MessageResource::collection($messages),
        ], $this->apiMessage('find_all_success'), $messages->lastPage(), $messages->total());
    }
}
