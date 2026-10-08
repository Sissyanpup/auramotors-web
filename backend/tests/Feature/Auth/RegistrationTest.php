<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Budi Buyer',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'buyer');
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com', 'role' => 'buyer']);
    }

    public function test_seller_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Sinta Seller',
            'email' => 'sinta@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'seller',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'seller');
    }

    public function test_cannot_self_register_as_admin(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Fake Admin',
            'email' => 'fake-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'fake-admin@example.com']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'dup@example.com', 'role' => UserRole::Buyer]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
