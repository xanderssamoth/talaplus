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
        if (! Schema::hasTable('pricings') || Schema::hasColumn('pricings', 'premium_duration_months')) {
            return;
        }

        Schema::table('pricings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('premium_duration_months')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve columns already supplied by an imported SQL schema.
    }
};
