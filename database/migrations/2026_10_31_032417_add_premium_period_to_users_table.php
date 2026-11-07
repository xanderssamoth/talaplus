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
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'premium_started_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('premium_started_at')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'premium_expires_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('premium_expires_at')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'premium_duration_months')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedTinyInteger('premium_duration_months')->nullable();
            });
        }

        if (! Schema::hasIndex('users', 'users_premium_expires_at_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('premium_expires_at');
            });
        }
    }

    /**
     * Keep premium columns because they may already exist in an imported database.
     */
    public function down(): void {}
};
