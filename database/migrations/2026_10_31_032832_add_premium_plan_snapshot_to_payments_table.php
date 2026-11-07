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
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'premium_duration_months')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unsignedTinyInteger('premium_duration_months')->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'premium_plan_amount')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->decimal('premium_plan_amount', 12, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'premium_plan_currency')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('premium_plan_currency', 45)->nullable();
            });
        }

        if (! Schema::hasColumn('payments', 'premium_activated_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->timestamp('premium_activated_at')->nullable();
            });
        }
    }

    /**
     * Keep columns because imported installations may already have them.
     */
    public function down(): void {}
};
