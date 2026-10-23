<?php

namespace App\Http\Controllers\Api;

use App\Models\ChildInvitation;
use App\Services\ChildInvitationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildInvitationController extends ApiResourceController
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

        return $this->handleResponse($invitations, __('api.child_invitation.sent'));
    }

    public function index(Request $request): JsonResponse
    {
        $invitations = ChildInvitation::query()
            ->where('child_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with('parent:id,username,firstname,lastname')
            ->latest()
            ->get();

        return $this->handleResponse($invitations, __('api.child_invitation.listed'));
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

        return $this->handleResponse($result, __('api.child_invitation.'.($accept ? 'accepted' : 'declined')));
    }
}
