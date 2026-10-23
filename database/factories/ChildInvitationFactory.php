<?php

namespace Database\Factories;

use App\Models\ChildInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildInvitation>
 */
class ChildInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_user_id' => User::factory(),
            'child_user_id' => User::factory(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'responded_at' => null,
        ];
    }
}
