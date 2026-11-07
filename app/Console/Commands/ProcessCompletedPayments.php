<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\CoinService;
use App\Services\PaymentNotificationService;
use App\Services\PremiumService;
use Illuminate\Console\Command;
use Throwable;

class ProcessCompletedPayments extends Command
{
    protected $signature = 'payments:process-completed';

    protected $description = 'Process completed FlexPaie payments.';

    public function handle(CoinService $coinService, PremiumService $premiumService, PaymentNotificationService $paymentNotificationService): int
    {
        Payment::query()
            ->where('status', 0)
            ->where(function ($query) {
                $query
                    ->where('reason', 'coin_price')
                    ->whereNull('coins_credited_at')
                    ->orWhere(function ($query): void {
                        $query->where('reason', 'user_certfied')->whereNull('premium_activated_at');
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($coinService, $premiumService, $paymentNotificationService): void {
                foreach ($payments as $payment) {
                    try {
                        if ($payment->reason === 'user_certfied') {
                            $premiumService->completePayment($payment);
                            $paymentNotificationService->record($payment->fresh());
                        } else {
                            $coinService->completePayment($payment);
                        }
                    } catch (Throwable $exception) {
                        report($exception);

                        $this->error(
                            sprintf(
                                'Payment #%d failed: %s',
                                $payment->id,
                                $exception->getMessage()
                            )
                        );
                    }
                }
            });

        return self::SUCCESS;
    }
}
