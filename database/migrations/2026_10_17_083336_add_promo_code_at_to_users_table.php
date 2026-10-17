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
        if (! Schema::hasColumn('users', 'promo_code_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dateTime('promo_code_at')->nullable()->after('promo_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'promo_code_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('promo_code_at');
            });
        }
    }
};
