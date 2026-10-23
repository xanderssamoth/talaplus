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
        if (! Schema::hasColumn('users', 'child_of')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('child_of')->nullable()->after('belongs_to')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'child_of')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('child_of');
            });
        }
    }
};
