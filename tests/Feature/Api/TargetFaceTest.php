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
        $this->seed(\Database\Seeders\OrganizationSeeder::class);
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

        $response = $this->getJson('/api/v1/scoring/target-faces')
            ->assertOk()
            ->assertJsonCount(26, 'data');

        $data = $response->json('data');

        for ($i = 0; $i < count($data) - 1; $i++) {
            $this->assertTrue(
                $data[$i]['used_count'] >= $data[$i + 1]['used_count'],
                "Index {$i} ({$data[$i]['code']} count: {$data[$i]['used_count']}) has fewer participants than index " . ($i + 1) . " ({$data[$i+1]['code']} count: {$data[$i+1]['used_count']})"
            );
        }
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
