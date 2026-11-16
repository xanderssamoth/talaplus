<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Resources\Api\ChildInvitationResource;
use App\Models\ChildInvitation;
use App\Models\User;
use App\Services\ChildInvitationService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildInvitationController extends BaseController
{
    public function __construct(private ChildInvitationService $childInvitationService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'child_ids' => ['required', 'array', 'min:1', 'max:20'],
            'child_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        try {
            $invitations = $this->childInvitationService->invite($request->user(), $validated['child_ids']);
        } catch (DomainException $exception) {
            return $this->handleError(null, __($exception->getMessage()), 409);
        }

        return $this->handleResponse(
            ChildInvitationResource::collection($invitations->load($this->parentUserColumns())),
            __('api.child_invitation.sent')
        );
    }

    public function index(Request $request): JsonResponse
    {
        $invitations = $this->pendingInvitationsFor($request->user()->id);

        return $this->handleResponse(ChildInvitationResource::collection($invitations), __('api.child_invitation.listed'));
    }

    public function check(Request $request, int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        if ($request->user()?->id !== $user->id) {
            return $this->handleError(null, __('api.child_invitation.not_authorized'), 403);
        }

        $invitations = $this->pendingInvitationsFor($user->id);

        return $this->handleResponse([
            'has_pending_invitations' => $invitations->isNotEmpty(),
            'pending_invitations_count' => $invitations->count(),
            'invitations' => ChildInvitationResource::collection($invitations),
        ], __('api.child_invitation.listed'));
    }

    public function accept(Request $request, int $invitation): JsonResponse
    {
        return $this->respond($request, $invitation, true);
    }

    public function decline(Request $request, int $invitation): JsonResponse
    {
        return $this->respond($request, $invitation, false);
    }

    private function respond(Request $request, int $invitation, bool $accept): JsonResponse
    {
        try {
            $result = $this->childInvitationService->respond($request->user(), $invitation, $accept);
        } catch (DomainException $exception) {
            return $this->handleError(null, __($exception->getMessage()), $exception->getMessage() === 'api.child_invitation.not_authorized' ? 403 : 409);
        }

        return $this->handleResponse(
            ChildInvitationResource::make($result->load($this->parentUserColumns())),
            __('api.child_invitation.'.($accept ? 'accepted' : 'declined'))
        );
    }

    /**
     * @return Collection<int, ChildInvitation>
     */
    private function pendingInvitationsFor(int $userId): Collection
    {
        return ChildInvitation::query()
            ->where('child_user_id', $userId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with($this->parentUserColumns())
            ->latest()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function parentUserColumns(): array
    {
        return ['parent:id,firstname,lastname,surname,partner_name,username,avatar_url,cover_url'];
    }
}
