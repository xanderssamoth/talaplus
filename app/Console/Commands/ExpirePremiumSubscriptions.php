<?php

namespace App\Console\Commands;

use App\Services\PremiumService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('premium:expire')]
#[Description('Expire premium memberships past their expiration date')]
class ExpirePremiumSubscriptions extends Command
{
    public function handle(PremiumService $premiumService): int
    {
        $this->components->info(__('api.premium.expired_count', ['count' => $premiumService->expire()]));

        return self::SUCCESS;
    }
}
