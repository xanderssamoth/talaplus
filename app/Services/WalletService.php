<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['coins_balance' => 0]
        );
    }

    public function creditCoins(User $user, int $amount): Wallet
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('api.wallet.credit_amount_invalid'));
        }

        return DB::transaction(function () use ($user, $amount): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::query()->create([
                    'user_id' => $user->id,
                    'coins_balance' => 0,
                ]);

                $wallet = Wallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $wallet->increment('coins_balance', $amount);

            return $wallet->refresh();
        });
    }

    public function debitCoins(User $user, int $amount): Wallet
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('api.wallet.debit_amount_invalid'));
        }

        return DB::transaction(function () use ($user, $amount): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException(__('api.wallet.not_found_for_user'));
            }

            if ($wallet->coins_balance < $amount) {
                throw new RuntimeException(__('api.wallet.insufficient_coins'));
            }

            $wallet->decrement('coins_balance', $amount);

            return $wallet->refresh();
        });
    }
}
