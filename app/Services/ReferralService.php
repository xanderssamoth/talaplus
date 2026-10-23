<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReferralService
{
    /**
     * @return array{promo_code: string, promo_code_at: Carbon}
     */
    public function draft(): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $letters = '';
            $digits = '';

            for ($index = 0; $index < 5; $index++) {
                $letters .= chr(random_int(65, 90));
                $digits .= (string) random_int(0, 9);
            }

            $code = $letters.$digits;

            if (! User::withTrashed()->where('promo_code', $code)->exists()
                && ! PromoCode::query()->where('code', $code)->exists()) {
                return ['promo_code' => $code, 'promo_code_at' => now()];
            }
        }

        throw new RuntimeException(__('api.referral.generation_failed'));
    }

    public function activate(User $user): User
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                DB::transaction(function () use ($user): void {
                    $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                    $draft = $this->draft();
                    $activatedAt = $draft['promo_code_at'];

                    PromoCode::query()
                        ->where('user_id', $lockedUser->id)
                        ->where('status', 'active')
                        ->update(['status' => 'expired']);

                    $lockedUser->forceFill($draft)->save();
                    PromoCode::create([
                        'code' => $draft['promo_code'],
                        'validity' => $activatedAt->copy()->addMonthsNoOverflow(2)->getTimestamp() - $activatedAt->getTimestamp(),
                        'status' => 'active',
                        'user_id' => $lockedUser->id,
                    ]);
                });

                return $user->refresh();
            } catch (UniqueConstraintViolationException) {
                $user->refresh();
            }
        }

        throw new RuntimeException(__('api.referral.generation_failed'));
    }

    public function terminate(User $user): User
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            PromoCode::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $lockedUser->forceFill(['promo_code' => null])->save();
        });

        return $user->refresh();
    }

    public function expire(): int
    {
        $expiredCount = 0;
        $cutoff = now()->subMonthsNoOverflow(2);

        User::query()
            ->whereNotNull('promo_code')
            ->whereNotNull('promo_code_at')
            ->where('promo_code_at', '<', $cutoff)
            ->chunkById(500, function ($users) use ($cutoff, &$expiredCount): void {
                $expiredCount += DB::transaction(function () use ($users, $cutoff): int {
                    $userIds = User::query()
                        ->whereKey($users->modelKeys())
                        ->whereNotNull('promo_code')
                        ->whereNotNull('promo_code_at')
                        ->where('promo_code_at', '<', $cutoff)
                        ->lockForUpdate()
                        ->pluck('id');

                    if ($userIds->isEmpty()) {
                        return 0;
                    }

                    PromoCode::query()
                        ->whereIn('user_id', $userIds)
                        ->where('status', 'active')
                        ->update(['status' => 'expired']);

                    User::query()->whereKey($userIds)->update(['promo_code' => null]);

                    return $userIds->count();
                });
            });

        return $expiredCount;
    }
}
