<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'type') || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN type ENUM('uncertified', 'certifying', 'certified') NOT NULL DEFAULT 'uncertified'");
    }

    /**
     * Preserve the new enum value because pending memberships may use it.
     */
    public function down(): void {}
};
