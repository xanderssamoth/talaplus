<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class GroupController extends ApiResourceController
{
    protected string $modelClass = Group::class;

    protected string $resourceClass = GroupResource::class;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_name' => ['required', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $group = DB::transaction(function () use ($validated): Group {
            $group = Group::create($validated);

            if ($group->user_id !== null) {
                $group->users()->attach($group->user_id, ['is_admin' => true]);
            }

            return $group;
        });

        return $this->handleResponse(
            GroupResource::make($group->refresh()),
            $this->apiMessage('created')
        );
    }

    public function userGroups(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $groups = Group::query()
            ->where('user_id', $user->id)
            ->orWhereHas('users', fn ($query) => $query->whereKey($user->id))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->handleResponse(
            GroupResource::collection($groups),
            $this->apiMessage('find_all_success'),
            $groups->lastPage(),
            $groups->total()
        );
    }
}
