<?php

namespace App\Console\Commands;

use App\Services\ReferralService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('referrals:expire')]
#[Description('Expire referral codes older than two months')]
class ExpireReferralCodes extends Command
{
    public function handle(ReferralService $referralService): int
    {
        $this->components->info(__('api.referral.expired_count', ['count' => $referralService->expire()]));

        return self::SUCCESS;
    }
}
