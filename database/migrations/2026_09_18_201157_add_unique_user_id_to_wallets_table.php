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
        if (! Schema::hasTable('wallets')) {
            return;
        }

        Schema::table('wallets', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('wallets')) {
            return;
        }

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
