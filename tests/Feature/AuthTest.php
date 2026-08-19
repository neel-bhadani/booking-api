<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Neel',
            'email' => 'neel@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'neel@test.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'neel@test.com']);

        $this->postJson('/api/register', [
            'name' => 'Neel',
            'email' => 'neel@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_short_password(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Neel',
            'email' => 'neel@test.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        // Arrange — create a user with a known password
        $user = User::factory()->create([
            'email' => 'neel@test.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'neel@test.com',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        // Arrange — user exists with a known password
        $user = User::factory()->create([
            'email' => 'neel@test.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'neel@test.com',
            'password' => 'passwod52',
        ])->assertStatus(401)
            ->assertJsonMissing(['token']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act + Assert
        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_logout_revokes_token(): void
    {
        // Arrange — real token, not actingAs()
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        // Act — logout using that token
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        // Assert — the same token no longer works
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
