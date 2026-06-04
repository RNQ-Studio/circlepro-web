<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\TargetFaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TargetFaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TargetFaceSeeder::class);
    }

    public function test_guest_cannot_access_target_faces(): void
    {
        $this->getJson('/api/v1/scoring/target-faces')
            ->assertUnauthorized();
    }

    public function test_user_can_list_target_faces(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/scoring/target-faces')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.code', 'fita_122')
            ->assertJsonPath('data.4.code', 'jemparingan');
    }

    public function test_user_can_get_bow_classes(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/scoring/bow-classes')
            ->assertOk()
            ->assertJsonPath('data.traditional.0.value', 'horsebow')
            ->assertJsonPath('data.modern.0.value', 'recurve');
    }
}
