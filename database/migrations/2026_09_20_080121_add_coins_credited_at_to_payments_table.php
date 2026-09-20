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
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'coins_credited_at')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('coins_credited_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'coins_credited_at')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('coins_credited_at');
        });
    }
};
