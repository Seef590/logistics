<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_matches_the_actual_database_schema(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'expediteur']);
    }

    public function test_registration_requires_a_strong_enough_password_and_unique_phone(): void
    {
        User::factory()->create(['phone' => '0612345678']);

        $base = [
            'name' => 'Nouvel utilisateur',
            'email' => 'new@example.test',
            'phone' => '0612345678',
            'password' => 'short',
            'role' => 'expediteur',
            'city' => 'Casablanca',
        ];

        $this->postJson('/api/auth/register', $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'password']);
    }

    public function test_banned_account_is_blocked_even_when_its_token_already_exists(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('existing')->plainTextToken;
        $user->update(['is_banned' => true]);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error', 'Compte suspendu définitivement');
    }

    public function test_temporarily_suspended_account_is_blocked(): void
    {
        $user = User::factory()->create(['suspended_until' => now()->addHour()]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error', 'Compte suspendu temporairement');
    }

    public function test_non_carrier_cannot_set_online_presence(): void
    {
        $user = User::factory()->create(['role' => 'expediteur', 'is_online' => false]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/me', ['is_online' => true])->assertOk();

        $this->assertFalse($user->fresh()->is_online);
    }
}
