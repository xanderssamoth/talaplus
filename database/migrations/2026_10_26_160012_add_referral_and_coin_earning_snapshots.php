<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'referral_code_used')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('referral_code_used', 45)->nullable();
            });
        }

        if (Schema::hasTable('payments')) {
            if (! Schema::hasColumn('payments', 'coins_amount')) {
                Schema::table('payments', function (Blueprint $table): void {
                    $table->bigInteger('coins_amount')->nullable();
                });
            }

            if (! Schema::hasColumn('payments', 'coin_unit_price')) {
                Schema::table('payments', function (Blueprint $table): void {
                    $table->decimal('coin_unit_price', 12, 6)->nullable();
                });
            }
        }

        if (Schema::hasTable('gift_transactions')) {
            foreach (['gross_amount', 'platform_fee_amount', 'creator_earning_amount'] as $column) {
                if (! Schema::hasColumn('gift_transactions', $column)) {
                    Schema::table('gift_transactions', function (Blueprint $table) use ($column): void {
                        $table->decimal($column, 12, 4)->nullable();
                    });
                }
            }

            if (! Schema::hasColumn('gift_transactions', 'currency')) {
                Schema::table('gift_transactions', function (Blueprint $table): void {
                    $table->string('currency', 45)->nullable();
                });
            }
        }
    }

    /**
     * Keep columns because they may have existed before this migration in imported databases.
     */
    public function down(): void {}
};
