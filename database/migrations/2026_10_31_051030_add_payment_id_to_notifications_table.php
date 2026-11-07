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
        if (! Schema::hasTable('notifications') || Schema::hasColumn('notifications', 'payment_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'payment_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_payment_id_unique');
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
