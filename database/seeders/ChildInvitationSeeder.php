<?php

namespace Database\Seeders;

use App\Models\ChildInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChildInvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->whereNull('child_of')->orderBy('id')->limit(2)->get();

        if ($users->count() === 2) {
            ChildInvitation::factory()->create([
                'parent_user_id' => $users[0]->id,
                'child_user_id' => $users[1]->id,
            ]);
        }
    }
}
