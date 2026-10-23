<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userIdColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'id');
        $userIdIsUnsigned = str_contains(strtolower($userIdColumn['type'] ?? ''), 'unsigned');

        Schema::create('child_invitations', function (Blueprint $table) use ($userIdIsUnsigned): void {
            $table->id();

            if ($userIdIsUnsigned) {
                $table->unsignedBigInteger('parent_user_id');
                $table->unsignedBigInteger('child_user_id');
            } else {
                $table->bigInteger('parent_user_id');
                $table->bigInteger('child_user_id');
            }
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique('id', 'id_childinvitations_UNIQUE');
            $table->index('parent_user_id', 'fk_childinvitations_parentusers_idx');
            $table->index('child_user_id', 'fk_childinvitations_childusers_idx');
            $table->foreign('parent_user_id', 'fk_childinvitations_parentusers')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('child_user_id', 'fk_childinvitations_childusers')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_invitations');
    }
};
