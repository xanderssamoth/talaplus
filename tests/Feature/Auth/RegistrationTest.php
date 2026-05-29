<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        File::ensureDirectoryExists(storage_path('framework/testing/views'));
        config(['view.compiled' => storage_path('framework/testing/views')]);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->createRoleTables();

        $response = $this->post('/register', [
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('roles', [
            'role_name->fr' => 'Administrateur',
            'role_description->fr' => 'Gestion des données de fonctionnement de la plateforme',
        ]);
        $this->assertDatabaseHas('role_user', [
            'is_selected' => 1,
        ]);
    }

    public function test_registration_is_forbidden_when_selected_administrator_exists(): void
    {
        $this->createRoleTables();

        $admin = User::factory()->create();
        $roleId = (int) DB::table('roles')->insertGetId([
            'role_name' => json_encode(['fr' => 'Administrateur']),
            'role_description' => json_encode(['fr' => 'Gestion']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $admin->id,
            'is_selected' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/register')->assertForbidden();

        $this->get('/login')
            ->assertOk()
            ->assertDontSee(route('register'));
    }

    private function createRoleTables(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->json('role_name');
            $table->json('role_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
        });
    }
}
