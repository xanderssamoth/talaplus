<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'firstname' => 'Test',
                'lastname' => 'User',
                'surname' => 'Middle',
                'username' => 'test_user',
                'email' => 'test@example.com',
                'phone' => '+243999000111',
                'gender' => 'male',
                'birthdate' => '1990-01-02',
                'country' => 'RDC',
                'city' => 'Kinshasa',
                'currency' => 'USD',
                'p_o_box' => '123',
                'address_1' => 'Adresse principale',
                'address_2' => 'Adresse secondaire',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test', $user->firstname);
        $this->assertSame('User', $user->lastname);
        $this->assertSame('Middle', $user->surname);
        $this->assertSame('test_user', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('+243999000111', $user->phone);
        $this->assertSame('male', $user->gender);
        $this->assertSame('RDC', $user->country);
        $this->assertSame('Kinshasa', $user->city);
        $this->assertSame('USD', $user->currency);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'firstname' => 'Test',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_account_identity_form_redirects_back_to_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                '_redirect' => 'account',
                'firstname' => 'Grace',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account'));

        $this->assertSame('Grace', $user->refresh()->firstname);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
