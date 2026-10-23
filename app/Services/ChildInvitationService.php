<?php

namespace App\Services;

use App\Models\ChildInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChildInvitationService
{
    /**
     * @param  list<int>  $childIds
     * @return Collection<int, ChildInvitation>
     */
    public function invite(User $parent, array $childIds): Collection
    {
        return DB::transaction(function () use ($parent, $childIds): Collection {
            $parent = User::query()->lockForUpdate()->findOrFail($parent->id);

            if ($parent->child_of !== null) {
                throw new DomainException('api.child_invitation.parent_is_child');
            }

            if (in_array($parent->id, $childIds, true)) {
                throw new DomainException('api.child_invitation.self_invitation');
            }

            $children = User::query()->whereKey($childIds)->orderBy('id')->lockForUpdate()->get();

            if ($children->count() !== count($childIds)) {
                throw new DomainException('api.child_invitation.child_not_found');
            }

            if ($children->contains(fn (User $child): bool => $child->child_of !== null)) {
                throw new DomainException('api.child_invitation.already_linked');
            }

            foreach ($children as $child) {
                $invitation = ChildInvitation::query()->firstOrNew([
                    'parent_user_id' => $parent->id,
                    'child_user_id' => $child->id,
                ]);

                if ($invitation->exists && $invitation->status === 'pending' && $invitation->expires_at?->isFuture()) {
                    continue;
                }

                $invitation->fill([
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                    'responded_at' => null,
                ])->save();
            }

            return ChildInvitation::query()
                ->where('parent_user_id', $parent->id)
                ->whereIn('child_user_id', $childIds)
                ->orderBy('child_user_id')
                ->get();
        });
    }

    public function respond(User $child, int $invitationId, bool $accept): ChildInvitation
    {
        return DB::transaction(function () use ($child, $invitationId, $accept): ChildInvitation {
            $invitation = ChildInvitation::query()->lockForUpdate()->findOrFail($invitationId);

            if ($invitation->child_user_id !== $child->id) {
                throw new DomainException('api.child_invitation.not_authorized');
            }

            if ($invitation->status !== 'pending') {
                throw new DomainException('api.child_invitation.not_pending');
            }

            if ($invitation->expires_at === null || $invitation->expires_at->isPast()) {
                throw new DomainException('api.child_invitation.expired');
            }

            $child = User::query()->lockForUpdate()->findOrFail($child->id);

            if ($child->child_of !== null) {
                throw new DomainException('api.child_invitation.already_linked');
            }

            if ($accept) {
                $parent = User::query()->lockForUpdate()->find($invitation->parent_user_id);

                if ($parent === null || $parent->child_of !== null) {
                    throw new DomainException('api.child_invitation.parent_unavailable');
                }

                $child->forceFill(['child_of' => $parent->id])->save();

                ChildInvitation::query()
                    ->where('child_user_id', $child->id)
                    ->where('status', 'pending')
                    ->where('id', '!=', $invitation->id)
                    ->update(['status' => 'declined', 'responded_at' => now()]);
            }

            $invitation->fill([
                'status' => $accept ? 'accepted' : 'declined',
                'responded_at' => now(),
            ])->save();

            return $invitation->refresh();
        });
    }
}
