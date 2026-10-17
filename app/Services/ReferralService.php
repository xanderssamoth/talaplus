<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
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

            if (! User::query()->where('promo_code', $code)->exists()) {
                return ['promo_code' => $code, 'promo_code_at' => now()];
            }
        }

        throw new RuntimeException(__('api.referral.generation_failed'));
    }

    public function activate(User $user): User
    {
        $user->forceFill($this->draft())->save();

        return $user->refresh();
    }

    public function terminate(User $user): User
    {
        $user->forceFill(['promo_code' => null])->save();

        return $user->refresh();
    }

    public function expire(): int
    {
        return User::query()
            ->whereNotNull('promo_code')
            ->whereNotNull('promo_code_at')
            ->where('promo_code_at', '<', now()->subMonthsNoOverflow(2))
            ->update(['promo_code' => null]);
    }
}
