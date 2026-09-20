<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Pricing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoinService
{
    public function __construct(
        protected WalletService $walletService,
        protected FlexPayService $flexPayService,
    ) {}

    public function getPackages(): Collection
    {
        return Pricing::query()
            ->where('reason', 'coin_price')
            ->whereNotNull('coins_amount')
            ->where('coins_amount', '>', 0)
            ->whereNotNull('pricing_cost')
            ->where('pricing_cost', '>', 0)
            ->with('descriptions')
            ->orderBy('pricing_cost')
            ->get();
    }

    public function purchase(User $user, Pricing $pricing, array $paymentAttributes): array
    {
        if ($pricing->reason !== 'coin_price') {
            throw new InvalidArgumentException('The selected pricing is not a Coins package.');
        }

        if ($pricing->coins_amount === null || (int) $pricing->coins_amount <= 0) {
            throw new InvalidArgumentException('The Coins package has an invalid Coins amount.');
        }

        if ($pricing->pricing_cost === null || (float) $pricing->pricing_cost <= 0) {
            throw new InvalidArgumentException('The Coins package has an invalid price.');
        }

        if (blank($pricing->currency)) {
            throw new InvalidArgumentException('The Coins package has no currency.');
        }

        return $this->flexPayService->initiate([
            ...$paymentAttributes,
            'user_id' => $user->id,
            'amount' => $pricing->pricing_cost,
            'currency' => strtoupper((string) $pricing->currency),
            'reason' => 'coin_price',
            'entity' => 'pricing',
            'entity_id' => $pricing->id,
        ]);
    }

    /**
     * Credit the user's wallet after FlexPaie confirms
     * that the payment has been completed.
     */
    public function completePayment(Payment $payment): void
    {
        if ($payment->reason !== 'coin_price') {
            return;
        }

        /*
         * FlexPaie:
         *
         * status = 0 -> paiement abouti
         * status = 1 -> paiement non abouti
         */
        if ((int) $payment->status !== 0) {
            return;
        }

        if ($payment->user_id === null) {
            throw new InvalidArgumentException('The payment has no associated user.');
        }

        if ($payment->entity !== 'pricing' || $payment->entity_id === null) {
            throw new InvalidArgumentException('The payment has no associated Coins package.');
        }

        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            /*
             * The payment could have changed between the
             * initial query and this locked query.
             */
            if ((int) $payment->status !== 0) {
                return;
            }

            /*
             * Prevent duplicate Coin credits.
             */
            if ($payment->coins_credited_at !== null) {
                return;
            }

            $pricing = Pricing::query()->findOrFail($payment->entity_id);

            if ($pricing->reason !== 'coin_price') {
                throw new InvalidArgumentException('The payment pricing is not a Coins package.');
            }

            $coinsAmount = (int) $pricing->coins_amount;

            if ($coinsAmount <= 0) {
                throw new InvalidArgumentException('The Coins package has an invalid Coins amount.');
            }

            $user = User::query()->findOrFail($payment->user_id);

            $this->walletService->creditCoins($user, $coinsAmount);

            $payment->forceFill([
                'coins_credited_at' => now(),
            ])->save();
        });
    }
}
