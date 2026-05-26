<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        File::ensureDirectoryExists(storage_path('framework/testing/views'));
        config(['view.compiled' => storage_path('framework/testing/views')]);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Schema::create('roles', function ($table): void {
            $table->id();
            $table->json('role_name');
            $table->json('role_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_user', function ($table): void {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->boolean('is_selected')->default(false);
            $table->timestamps();
        });

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
            'role_description->fr' => 'Gestion des donnees de fonctionnement de la plateforme',
        ]);
        $this->assertDatabaseHas('role_user', [
            'is_selected' => 1,
        ]);
    }
}
