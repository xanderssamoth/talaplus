<?php

namespace App\Services;

use App\Models\GiftTransaction;
use App\Models\History;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GiftService
{
    public function __construct(protected WalletService $walletService) {}

    public function getAvailableGifts()
    {
        return Pricing::query()
            ->where('reason', 'gift_sent')
            ->whereNotNull('pricing_cost')
            ->where('pricing_cost', '>', 0)
            ->with('descriptions')
            ->orderBy('pricing_cost')
            ->get();
    }

    public function sendGift(User $sender, Media $media, Pricing $pricing, int $quantity = 1): GiftTransaction
    {
        if ($pricing->reason !== 'gift_sent') {
            throw new InvalidArgumentException(__('api.gift.invalid_pricing'));
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException(__('api.gift.quantity_invalid'));
        }

        if ($media->user_id === null) {
            throw new InvalidArgumentException(__('api.gift.media_owner_missing'));
        }

        $receiver = User::query()->find($media->user_id);

        if ($receiver === null) {
            throw new InvalidArgumentException(__('api.gift.receiver_not_found'));
        }

        if ($sender->id === $receiver->id) {
            throw new InvalidArgumentException(__('api.gift.self_not_allowed'));
        }

        $giftCost = (int) $pricing->pricing_cost;

        if ($giftCost <= 0) {
            throw new InvalidArgumentException(__('api.gift.coin_price_invalid'));
        }

        $totalCoins = $giftCost * $quantity;

        return DB::transaction(function () use ($sender, $receiver, $media, $pricing, $quantity, $totalCoins): GiftTransaction {
            $lastCoinPurchase = Payment::query()
                ->where('user_id', $sender->id)
                ->where('reason', 'coin_price')
                ->where('status', 0)
                ->whereNotNull('coins_credited_at')
                ->orderByDesc('coins_credited_at')
                ->orderByDesc('id')
                ->first();

            if ($lastCoinPurchase === null || (float) $lastCoinPurchase->coin_unit_price <= 0 || blank($lastCoinPurchase->currency)) {
                throw new InvalidArgumentException(__('api.gift.coin_purchase_missing'));
            }

            $grossAmount = round($totalCoins * (float) $lastCoinPurchase->coin_unit_price, 4);
            $platformFeeAmount = round($grossAmount * 0.3, 4);

            $this->walletService->debitCoins(
                $sender,
                $totalCoins
            );

            $history = History::query()->create([
                'user_id' => $sender->id,
                'entity' => 'media',
                'entity_id' => $media->id,
                'action' => 'gift',
            ]);

            Reaction::query()->create([
                'type' => 'gift',
                'pricing_id' => $pricing->id,
                'media_id' => $media->id,
                'user_id' => $sender->id,
            ]);

            return GiftTransaction::query()->create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'pricing_id' => $pricing->id,
                'quantity' => $quantity,
                'coins_amount' => $totalCoins,
                'gross_amount' => $grossAmount,
                'platform_fee_amount' => $platformFeeAmount,
                'creator_earning_amount' => round($grossAmount - $platformFeeAmount, 4),
                'currency' => strtoupper($lastCoinPurchase->currency),
                'history_id' => $history->id,
            ]);
        });
    }

    public function sentGifts(User $user)
    {
        return GiftTransaction::query()
            ->where('sender_id', $user->id)
            ->with([
                'receiver',
                'pricing',
                'history',
            ])
            ->latest('id')
            ->paginate(20);
    }

    public function receivedGifts(User $user)
    {
        return GiftTransaction::query()
            ->where('receiver_id', $user->id)
            ->with([
                'sender',
                'pricing',
                'history',
            ])
            ->latest('id')
            ->paginate(20);
    }
}
