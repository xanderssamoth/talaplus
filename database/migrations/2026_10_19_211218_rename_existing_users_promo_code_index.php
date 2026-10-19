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
        $index = collect(Schema::getIndexes('users'))
            ->first(fn (array $index): bool => $index['unique'] && $index['columns'] === ['promo_code']);

        if ($index !== null && strcasecmp($index['name'], 'promo_code_UNIQUE') !== 0) {
            Schema::table('users', function (Blueprint $table) use ($index): void {
                $table->renameIndex($index['name'], 'promo_code_UNIQUE');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous index name depends on when the database was created.
    }
};
