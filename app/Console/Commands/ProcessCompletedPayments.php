<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\CoinService;
use Illuminate\Console\Command;
use Throwable;

class ProcessCompletedPayments extends Command
{
    protected $signature = 'payments:process-completed';

    protected $description = 'Process completed FlexPaie payments.';

    public function handle(CoinService $coinService): int
    {
        Payment::query()
            ->where('status', 0)
            ->where(function ($query) {
                $query
                    ->where('reason', 'coin_price')
                    ->whereNull('coins_credited_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($coinService): void {
                foreach ($payments as $payment) {
                    try {
                        $coinService->completePayment($payment);
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
