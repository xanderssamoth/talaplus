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
        $existingIndex = collect(Schema::getIndexes('users'))
            ->first(fn (array $index): bool => $index['unique'] && $index['columns'] === ['promo_code']);

        if ($existingIndex !== null) {
            if (strcasecmp($existingIndex['name'], 'promo_code_UNIQUE') !== 0) {
                Schema::table('users', function (Blueprint $table) use ($existingIndex): void {
                    $table->renameIndex($existingIndex['name'], 'promo_code_UNIQUE');
                });
            }

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('promo_code', 'promo_code_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $index = collect(Schema::getIndexes('users'))
            ->first(fn (array $index): bool => strcasecmp($index['name'], 'promo_code_UNIQUE') === 0);

        if ($index !== null) {
            Schema::table('users', function (Blueprint $table) use ($index): void {
                $table->dropUnique($index['name']);
            });
        }
    }
};
