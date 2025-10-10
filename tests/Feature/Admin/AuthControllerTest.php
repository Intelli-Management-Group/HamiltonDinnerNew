<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function login_requires_email_and_password()
    {
        $response = $this->postJson('/api/admin/login', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'fake@example.com',
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'Email or Password is incorrect']);
    }

    /** @test */
    public function login_succeeds_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);
        $response = $this->postJson('/api/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user',
            'permissions',
            'ResponseCode',
            'ResponseText',
        ]);
    }

    /** @test */
    public function register_requires_all_fields()
    {
        $response = $this->postJson('/api/admin/register', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'user_name', 'email', 'password', 'role_id']);
    }

    /** @test */
    public function me_returns_authenticated_user()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/me');
        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $user->id]);
    }

    /** @test */
    public function logout_invalidates_token()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/logout');
        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Successfully logged out']);
    }

    /** @test */
    public function refresh_returns_new_token()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/refresh');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user',
            'ResponseCode',
            'ResponseText',
        ]);
    }
}
