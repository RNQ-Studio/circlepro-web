<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated',
                'data' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_profile_email_must_be_unique_except_for_current_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'current@example.com']);
        Passport::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'Current User',
            'email' => 'taken@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->putJson('/api/v1/auth/me', [
            'name' => 'Current User',
            'email' => 'current@example.com',
        ])->assertOk();
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/me', [
            'name' => 'Guest',
            'email' => 'guest@example.com',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Passport::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Password changed']);

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Passport::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }
}
