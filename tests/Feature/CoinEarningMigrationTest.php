<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoinEarningMigrationTest extends TestCase
{
    public function test_snapshot_columns_are_added_without_replacing_existing_columns(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('referral_code_used')->nullable();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('gift_transactions', function (Blueprint $table): void {
            $table->id();
            $table->decimal('gross_amount', 12, 4)->nullable();
        });

        $migration = require database_path('migrations/2026_10_26_160012_add_referral_and_coin_earning_snapshots.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('users', ['referral_code_used']));
        $this->assertTrue(Schema::hasColumns('payments', ['coins_amount', 'coin_unit_price']));
        $this->assertTrue(Schema::hasColumns('gift_transactions', ['gross_amount', 'platform_fee_amount', 'creator_earning_amount', 'currency']));

        $migration->down();
        $this->assertTrue(Schema::hasColumn('users', 'referral_code_used'));
    }
}
