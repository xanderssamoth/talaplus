<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\GiftTransaction;
use App\Models\History;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EarningStatsService
{
    public function __construct(private ExchangeRateService $exchangeRateService) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $lastPayout = Payment::query()
            ->where('reason', 'earnings_send')
            ->where('entity', 'user')
            ->where('entity_id', $user->id)
            ->where('status', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'created_at']);

        $cutoff = $lastPayout?->created_at;
        $giftBuckets = [];
        $marketplaceBuckets = [];
        $referralBuckets = [];

        $this->collectGiftEarnings($user, $cutoff, $giftBuckets);
        $this->collectMarketplaceEarnings($user, $cutoff, $marketplaceBuckets);
        $this->collectReferralEarnings($user, $cutoff, $referralBuckets);

        $followerIds = Subscription::query()
            ->where('user_id', $user->id)
            ->select('follower_id');

        $referredFollowers = User::query()
            ->where('belongs_to', $user->id)
            ->whereNotNull('referral_code_used')
            ->where('referral_code_used', $user->promo_code)
            ->whereIn('id', $followerIds)
            ->orderBy('id')
            ->get(['id', 'username', 'firstname', 'lastname', 'avatar_url']);

        $giftStats = $this->section($giftBuckets);
        $marketplaceStats = $this->section($marketplaceBuckets);
        $referralStats = $this->section($referralBuckets);

        return [
            'currency' => 'USD',
            'last_payout_at' => $cutoff?->toIso8601String(),
            'total_usd' => round($giftStats['total_usd'] + $marketplaceStats['total_usd'] + $referralStats['total_usd'], 2),
            'gift_on_media' => $giftStats,
            'marketplace' => [
                ...$marketplaceStats,
                'allocation_method' => 'proportional_order_snapshot',
                'estimated' => true,
            ],
            'referral' => [
                ...$referralStats,
                'referred_followers' => $referredFollowers,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     */
    private function collectGiftEarnings(User $user, ?CarbonInterface $cutoff, array &$buckets): void
    {
        $giftTable = (new GiftTransaction)->getTable();
        $historyTable = (new History)->getTable();
        $coinGifts = GiftTransaction::query()
            ->join($historyTable, "{$historyTable}.id", '=', "{$giftTable}.history_id")
            ->where("{$giftTable}.receiver_id", $user->id)
            ->where("{$historyTable}.entity", 'media')
            ->where("{$historyTable}.action", 'gift')
            ->when($cutoff !== null, fn ($query) => $query->where("{$giftTable}.created_at", '>', $cutoff))
            ->select(["{$giftTable}.id", "{$giftTable}.creator_earning_amount", "{$giftTable}.currency", "{$giftTable}.created_at"])
            ->cursor();

        foreach ($coinGifts as $gift) {
            if ($gift->creator_earning_amount === null || blank($gift->currency)) {
                throw new RuntimeException(__('api.earnings.gift_valuation_missing'));
            }

            $this->add($buckets, $gift->created_at, $this->usdAmount($gift->creator_earning_amount, $gift->currency));
        }
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     */
    private function collectMarketplaceEarnings(User $user, ?CarbonInterface $cutoff, array &$buckets): void
    {
        $paymentTable = (new Payment)->getTable();
        $orderTable = (new CustomerOrder)->getTable();
        $productTable = (new Product)->getTable();

        $rows = DB::table("{$paymentTable} as payment")
            ->join("{$orderTable} as order", 'order.cart_id', '=', 'payment.entity_id')
            ->join("{$productTable} as product", 'product.id', '=', 'order.product_id')
            ->where('payment.reason', 'product_sale')
            ->where('payment.entity', 'cart')
            ->where('payment.status', 0)
            ->whereColumn('order.created_at', '<=', 'payment.created_at')
            ->where(fn ($query) => $query->whereNull('order.deleted_at')->orWhereColumn('order.deleted_at', '>', 'payment.created_at'))
            ->whereExists(function ($query) use ($orderTable, $productTable, $user): void {
                $query->selectRaw('1')
                    ->from("{$orderTable} as own_order")
                    ->join("{$productTable} as own_product", 'own_product.id', '=', 'own_order.product_id')
                    ->whereColumn('own_order.cart_id', 'payment.entity_id')
                    ->whereColumn('own_order.created_at', '<=', 'payment.created_at')
                    ->where(fn ($query) => $query->whereNull('own_order.deleted_at')->orWhereColumn('own_order.deleted_at', '>', 'payment.created_at'))
                    ->where('own_product.user_id', $user->id);
            })
            ->when($cutoff !== null, fn ($query) => $query->where('payment.created_at', '>', $cutoff))
            ->select([
                'payment.id as payment_id', 'payment.amount as payment_amount',
                'payment.currency as payment_currency', 'payment.created_at as payment_at',
                'order.price_at_that_time as order_price', 'order.currency as order_currency',
                'order.quantity', 'product.user_id as seller_id',
            ])
            ->orderBy('payment.id')
            ->cursor();

        $currentPaymentId = null;
        $cartWeight = 0.0;
        $sellerWeight = 0.0;
        $paymentAmount = 0.0;
        $paymentCurrency = null;
        $paymentAt = null;

        foreach ($rows as $row) {
            if ($currentPaymentId !== null && $currentPaymentId !== $row->payment_id) {
                $this->addMarketplacePayment($buckets, $paymentAt, $paymentAmount, $paymentCurrency, $sellerWeight, $cartWeight);
                $cartWeight = 0.0;
                $sellerWeight = 0.0;
            }

            $currentPaymentId = $row->payment_id;
            $paymentAmount = (float) $row->payment_amount;
            $paymentCurrency = $row->payment_currency;
            $paymentAt = $row->payment_at;

            if ($row->order_price === null || (int) $row->quantity < 1 || blank($row->order_currency)) {
                throw new RuntimeException(__('api.earnings.order_price_missing'));
            }

            $lineWeight = $this->usdAmount(
                (float) $row->order_price * (int) $row->quantity,
                $row->order_currency
            );
            $cartWeight += $lineWeight;

            if ((int) $row->seller_id === $user->id) {
                $sellerWeight += $lineWeight;
            }
        }

        if ($currentPaymentId !== null) {
            $this->addMarketplacePayment($buckets, $paymentAt, $paymentAmount, $paymentCurrency, $sellerWeight, $cartWeight);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     */
    private function addMarketplacePayment(array &$buckets, string $date, float $amount, ?string $currency, float $sellerWeight, float $cartWeight): void
    {
        if ($sellerWeight <= 0 || $cartWeight <= 0) {
            return;
        }

        $this->add($buckets, $date, $this->usdAmount($amount, $currency) * $sellerWeight / $cartWeight);
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     */
    private function collectReferralEarnings(User $user, ?CarbonInterface $cutoff, array &$buckets): void
    {
        $userTable = (new User)->getTable();

        $payments = Payment::query()
            ->join($userTable, "{$userTable}.id", '=', 'payments.user_id')
            ->where("{$userTable}.belongs_to", $user->id)
            ->whereNotNull("{$userTable}.referral_code_used")
            ->where("{$userTable}.referral_code_used", $user->promo_code)
            ->where('payments.status', 0)
            ->where(fn ($query) => $query->whereNull('payments.reason')->orWhere('payments.reason', '!=', 'earnings_send'))
            ->when($cutoff !== null, fn ($query) => $query->where('payments.created_at', '>', $cutoff))
            ->select(['payments.id', 'payments.amount', 'payments.currency', 'payments.created_at'])
            ->cursor();

        foreach ($payments as $payment) {
            $this->add($buckets, $payment->created_at, $this->usdAmount((float) $payment->amount * 0.1, $payment->currency));
        }
    }

    private function usdAmount(float|int|string|null $amount, ?string $currency): float
    {
        if ($amount === null || (float) $amount <= 0) {
            return 0.0;
        }

        if (blank($currency)) {
            throw new RuntimeException(__('api.earnings.currency_missing'));
        }

        return $this->exchangeRateService->convert((float) $amount, $currency, 'USD');
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     */
    private function add(array &$buckets, CarbonInterface|string $date, float $amountUsd): void
    {
        $cents = (int) round($amountUsd * 100);

        if ($cents <= 0) {
            return;
        }

        $day = CarbonImmutable::parse($date)->startOfDay();
        $week = $day->startOfWeek(CarbonInterface::MONDAY);
        $buckets[$week->toDateString()][$day->toDateString()] = ($buckets[$week->toDateString()][$day->toDateString()] ?? 0) + $cents;
    }

    /**
     * @param  array<string, array<string, int>>  $buckets
     * @return array{total_usd: float, weeks: array<int, array<string, mixed>>}
     */
    private function section(array $buckets): array
    {
        ksort($buckets);
        $totalCents = 0;
        $weeks = [];
        $dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($buckets as $weekDate => $amounts) {
            $start = CarbonImmutable::parse($weekDate);
            $days = [];
            $weekCents = 0;

            foreach ($dayKeys as $index => $key) {
                $date = $start->addDays($index)->toDateString();
                $cents = $amounts[$date] ?? 0;
                $weekCents += $cents;
                $days[] = [
                    'date' => $date,
                    'label' => __('api.earnings.days.'.$key),
                    'amount_usd' => round($cents / 100, 2),
                ];
            }

            $totalCents += $weekCents;
            $weeks[] = [
                'week' => $start->isoWeekYear.'-W'.str_pad((string) $start->isoWeek, 2, '0', STR_PAD_LEFT),
                'start_date' => $weekDate,
                'end_date' => $start->addDays(6)->toDateString(),
                'total_usd' => round($weekCents / 100, 2),
                'days' => $days,
            ];
        }

        return ['total_usd' => round($totalCents / 100, 2), 'weeks' => $weeks];
    }
}
