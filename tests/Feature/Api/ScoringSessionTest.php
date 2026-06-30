<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ScoringSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_scoring(): void
    {
        $this->getJson('/api/v1/scoring/sessions')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_user_can_crud_equipment_profile(): void
    {
        Passport::actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/scoring/equipment-profiles', [
            'name' => 'Recurve Latihan',
            'bow_class' => 'recurve',
            'draw_weight_lbs' => 28.5,
            'is_default' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Recurve Latihan')
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $this->getJson('/api/v1/scoring/equipment-profiles')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->putJson("/api/v1/scoring/equipment-profiles/{$id}", ['name' => 'Recurve Lomba'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Recurve Lomba');

        $this->deleteJson("/api/v1/scoring/equipment-profiles/{$id}")->assertOk();
    }

    public function test_storing_a_session_computes_aggregates(): void
    {
        Passport::actingAs(User::factory()->create());

        $payload = $this->sessionPayload(status: 'completed');

        $this->postJson('/api/v1/scoring/sessions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_score', 45)
            ->assertJsonPath('data.arrows_shot', 6)
            ->assertJsonPath('data.x_count', 1)
            ->assertJsonPath('data.ten_count', 2)
            ->assertJsonPath('data.miss_count', 1)
            ->assertJsonPath('data.max_possible_score', 60)
            ->assertJsonPath('data.avg_per_arrow', 7.5)
            ->assertJsonPath('data.is_personal_best', true)
            ->assertJsonPath('data.ends.0.end_total', 29);

        $this->assertDatabaseHas('personal_bests', [
            'bow_class' => 'recurve',
            'distance_category' => '70m',
            'num_arrows' => 6,
            'best_score' => 45,
        ]);
    }

    public function test_sighter_ends_are_stored_but_excluded_from_aggregates_and_pb(): void
    {
        Passport::actingAs(User::factory()->create());

        $payload = $this->sessionPayload(status: 'completed');
        $payload['num_ends'] = 3;
        array_unshift($payload['ends'], [
            'end_number' => 1,
            'is_sighter' => true,
            'arrows' => [
                ['arrow_index' => 0, 'score_value' => 10, 'is_x' => true, 'is_miss' => false],
                ['arrow_index' => 1, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                ['arrow_index' => 2, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
            ],
        ]);
        $payload['ends'][1]['end_number'] = 2;
        $payload['ends'][2]['end_number'] = 3;

        $this->postJson('/api/v1/scoring/sessions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.ends.0.is_sighter', true)
            ->assertJsonPath('data.ends.0.end_total', 30)
            ->assertJsonPath('data.total_score', 45)
            ->assertJsonPath('data.arrows_shot', 6)
            ->assertJsonPath('data.x_count', 1)
            ->assertJsonPath('data.ten_count', 2)
            ->assertJsonPath('data.max_possible_score', 60)
            ->assertJsonPath('data.avg_per_arrow', 7.5)
            ->assertJsonPath('data.is_personal_best', true);

        $this->assertDatabaseHas('personal_bests', [
            'bow_class' => 'recurve',
            'distance_category' => '70m',
            'num_arrows' => 6,
            'best_score' => 45,
        ]);
        $this->assertDatabaseMissing('personal_bests', [
            'num_arrows' => 9,
            'best_score' => 75,
        ]);
    }

    public function test_sync_is_idempotent_by_client_uuid(): void
    {
        Passport::actingAs(User::factory()->create());

        $payload = $this->sessionPayload(status: 'completed');
        $body = ['sessions' => [$payload]];

        $this->postJson('/api/v1/scoring/sessions/sync', $body)->assertOk();
        $this->postJson('/api/v1/scoring/sessions/sync', $body)->assertOk();

        // Same client_uuid → exactly one row.
        $this->assertSame(1, ScoringSession::query()->count());
    }

    public function test_summary_and_history_endpoints(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $id = $this->postJson('/api/v1/scoring/sessions', $this->sessionPayload(status: 'completed'))
            ->json('data.id');

        $this->getJson("/api/v1/scoring/sessions/{$id}/summary")
            ->assertOk()
            ->assertJsonPath('data.total_score', 45)
            ->assertJsonPath('data.consistency_index', fn ($v) => is_int($v));

        $this->getJson('/api/v1/scoring/sessions?filter[bow_class]=recurve')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->getJson('/api/v1/scoring/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_sessions', 1)
            ->assertJsonPath('data.total_arrows', 6);
    }

    public function test_user_cannot_view_another_users_session(): void
    {
        $owner = User::factory()->create();
        Passport::actingAs($owner);
        $id = $this->postJson('/api/v1/scoring/sessions', $this->sessionPayload())->json('data.id');

        Passport::actingAs(User::factory()->create());
        $this->getJson("/api/v1/scoring/sessions/{$id}")
            ->assertNotFound()
            ->assertJson(['code' => 'NOT_FOUND']);
    }

    /**
     * Two ends of three arrows: [X,10,9] = 29 and [8,8,M] = 16 → total 45.
     *
     * @return array<string, mixed>
     */
    private function sessionPayload(string $status = 'in_progress'): array
    {
        return [
            'id' => (string) Str::ulid(),
            'client_uuid' => (string) Str::uuid(),
            'bow_class' => 'recurve',
            'distance_category' => '70m',
            'distance_m' => 70,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 2,
            'arrows_per_end' => 3,
            'status' => $status,
            'source' => 'mobile',
            'started_at' => now()->toIso8601String(),
            'completed_at' => $status === 'completed' ? now()->toIso8601String() : null,
            'ends' => [
                [
                    'end_number' => 1,
                    'arrows' => [
                        ['arrow_index' => 0, 'score_value' => 10, 'is_x' => true, 'is_miss' => false],
                        ['arrow_index' => 1, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                        ['arrow_index' => 2, 'score_value' => 9, 'is_x' => false, 'is_miss' => false],
                    ],
                ],
                [
                    'end_number' => 2,
                    'arrows' => [
                        ['arrow_index' => 0, 'score_value' => 8, 'is_x' => false, 'is_miss' => false],
                        ['arrow_index' => 1, 'score_value' => 8, 'is_x' => false, 'is_miss' => false],
                        ['arrow_index' => 2, 'score_value' => 0, 'is_x' => false, 'is_miss' => true],
                    ],
                ],
            ],
        ];
    }
}
