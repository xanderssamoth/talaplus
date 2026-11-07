<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Pricing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PremiumService
{
    public function __construct(private FlexPayService $flexPayService) {}

    /**
     * @return array<int, array{pricing_id: int, name: string, duration: int, price: float, currency: string}>
     */
    public function plans(): array
    {
        $plans = [];

        $pricings = $this->premiumPlans()->latest('id')->get()->unique('premium_duration_months')->sortBy('premium_duration_months');

        foreach ($pricings as $pricing) {
            $months = (int) $pricing->premium_duration_months;
            $plans[$months] = [
                'pricing_id' => $pricing->id,
                'name' => $pricing->getTranslation('pricing_name', app()->getLocale(), false)
                    ?: $pricing->getTranslation('pricing_name', 'fr', false)
                    ?: (string) $months,
                'duration' => $months,
                'price' => (float) $pricing->pricing_cost,
                'currency' => strtoupper((string) $pricing->currency),
            ];
        }

        return $plans;
    }

    public function isPremium(User $user): bool
    {
        return $user->premium_expires_at !== null && $user->premium_expires_at->isFuture();
    }

    public function daysRemaining(User $user): int
    {
        if (! $this->isPremium($user)) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($user->premium_expires_at) / 86400);
    }

    /**
     * @param  array{type: int, phone?: ?string, channel?: ?string}  $paymentAttributes
     * @return array{payment: Payment, response: array<string, mixed>}
     */
    public function purchase(User $user, int $months, array $paymentAttributes): array
    {
        $plan = $this->premiumPlans()
            ->where('premium_duration_months', $months)
            ->latest('id')
            ->first();

        if ($plan === null) {
            throw new InvalidArgumentException(__('api.premium.invalid_plan'));
        }

        $result = $this->flexPayService->initiate([
            ...$paymentAttributes,
            'user_id' => $user->id,
            'amount' => $plan->pricing_cost,
            'currency' => strtoupper((string) $plan->currency),
            'description' => __('api.premium.payment_description', ['months' => $months]),
            'reason' => 'user_certfied',
            'entity' => 'user',
            'entity_id' => $user->id,
            'premium_duration_months' => $months,
            'premium_plan_amount' => $plan->pricing_cost,
            'premium_plan_currency' => strtoupper((string) $plan->currency),
        ]);

        DB::transaction(function () use ($user, $months, $result): void {
            $payment = Payment::query()->whereKey($result['payment']->id)->lockForUpdate()->firstOrFail();
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($payment->premium_activated_at === null) {
                $lockedUser->forceFill([
                    'premium_duration_months' => $months,
                    'type' => 'certifying',
                ])->save();
            }
        });

        return $result;
    }

    public function completePayment(Payment $payment): User
    {
        if ($payment->reason !== 'user_certfied' || $payment->entity !== 'user' || $payment->user_id === null || $payment->entity_id !== $payment->user_id || $payment->premium_duration_months === null) {
            throw new InvalidArgumentException(__('api.premium.payment_invalid'));
        }

        if ($payment->premium_activated_at !== null) {
            return User::query()->findOrFail($payment->user_id);
        }

        $response = $this->flexPayService->checkTransaction((string) $payment->order_number);
        $transaction = $response['transaction'] ?? null;

        if ((string) ($response['code'] ?? '') !== '0' || ! is_array($transaction)
            || (string) ($transaction['orderNumber'] ?? '') !== (string) $payment->order_number
            || (int) ($transaction['status'] ?? -1) !== 0
            || ! is_numeric($transaction['amount'] ?? null)
            || number_format((float) $transaction['amount'], 2, '.', '') !== $payment->premium_plan_amount
            || strtoupper((string) ($transaction['currency'] ?? '')) !== $payment->premium_plan_currency) {
            throw new RuntimeException(__('api.premium.payment_unverified'));
        }

        return DB::transaction(function () use ($payment): User {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->premium_activated_at !== null) {
                return User::query()->findOrFail($lockedPayment->user_id);
            }

            $user = User::query()->whereKey($lockedPayment->user_id)->lockForUpdate()->firstOrFail();
            $this->applyActivation($user, $lockedPayment->premium_duration_months);
            $lockedPayment->forceFill(['status' => 0, 'premium_activated_at' => now()])->save();

            return $user;
        });
    }

    public function settleFailedPayment(Payment $payment): void
    {
        if ($payment->reason !== 'user_certfied' || $payment->user_id === null || $payment->premium_activated_at !== null) {
            return;
        }

        $response = $this->flexPayService->checkTransaction((string) $payment->order_number);
        $transaction = $response['transaction'] ?? null;

        if ((string) ($response['code'] ?? '') !== '0' || ! is_array($transaction)
            || (string) ($transaction['orderNumber'] ?? '') !== (string) $payment->order_number
            || (int) ($transaction['status'] ?? -1) !== Payment::STATUS_FAILED) {
            throw new RuntimeException(__('api.premium.payment_unverified'));
        }

        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->premium_activated_at !== null) {
                return;
            }

            $user = User::query()->whereKey($lockedPayment->user_id)->lockForUpdate()->firstOrFail();
            $hasAnotherPendingPayment = $user->payments()
                ->where('reason', 'user_certfied')
                ->where('status', Payment::STATUS_PENDING)
                ->whereNull('premium_activated_at')
                ->whereKeyNot($lockedPayment->id)
                ->exists();

            $user->forceFill([
                'type' => $hasAnotherPendingPayment ? 'certifying' : ($this->isPremium($user) ? 'certified' : 'uncertified'),
            ])->save();
        });
    }

    public function expire(): int
    {
        return User::query()
            ->where('type', 'certified')
            ->whereNotNull('premium_expires_at')
            ->where('premium_expires_at', '<=', now())
            ->update(['type' => 'uncertified']);
    }

    private function applyActivation(User $user, int $months): User
    {
        if ($months < 1 || $months > 255) {
            throw new InvalidArgumentException(__('api.premium.payment_invalid'));
        }

        $startedAt = now();
        $baseDate = $user->premium_expires_at?->isFuture() ? $user->premium_expires_at->copy() : $startedAt->copy();

        $user->forceFill([
            'premium_started_at' => $startedAt,
            'premium_expires_at' => $baseDate->addMonthsNoOverflow($months),
            'premium_duration_months' => $months,
            'type' => 'certified',
        ])->save();

        return $user->refresh();
    }

    private function premiumPlans(): Builder
    {
        return Pricing::query()
            ->where('reason', 'user_certfied')
            ->where('pricing_type', 'money')
            ->whereNotNull('premium_duration_months')
            ->where('pricing_cost', '>', 0)
            ->whereIn('currency', ['USD', 'CDF']);
    }
}
