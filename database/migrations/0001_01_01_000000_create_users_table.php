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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('surname')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('gender', 45)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('p_o_box', 45)->nullable();
            $table->string('currency', 45)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 45)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('username')->nullable()->unique();
            $table->text('password')->nullable();
            $table->rememberToken();
            $table->text('api_token')->nullable();
            $table->text('api_key')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('promo_code', 45)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_email_confirmed_at')->nullable();
            $table->timestamp('two_factor_phone_confirmed_at')->nullable();
            $table->boolean('tips_at_every_login')->default(true);
            $table->boolean('is_online')->default(true);
            $table->boolean('christian_preference')->default(false);
            $table->string('status')->default('created');
            $table->string('type')->default('uncertified');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
