<?php

namespace Tests\Feature\Api;

use App\Models\Badge;
use App\Models\User;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Badge $levelBadge;

    private Badge $sessionBadge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Seed some test badges
        $this->levelBadge = Badge::query()->create([
            'name' => 'Pemula Handal',
            'description' => 'Mencapai Level 2',
            'icon_code' => 'grade',
            'requirement_type' => 'level',
            'requirement_value' => 2,
        ]);

        $this->sessionBadge = Badge::query()->create([
            'name' => 'Latihan Perdana',
            'description' => 'Menyelesaikan 1 sesi scoring',
            'icon_code' => 'play_arrow',
            'requirement_type' => 'sessions',
            'requirement_value' => 1,
        ]);
    }

    public function test_user_earns_xp_and_unlocks_badges_on_session_completion(): void
    {
        Passport::actingAs($this->user);

        // 1. Initial gamification stats should be empty/default
        $this->getJson('/api/v1/gamification/stats')
            ->assertOk()
            ->assertJsonPath('data.xp', 0)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.current_streak', 0)
            ->assertJsonPath('data.badges.0.unlocked', false)
            ->assertJsonPath('data.badges.1.unlocked', false);

        // 2. Complete a scoring session (12 arrows shot)
        // Set fixed time for consistency
        Carbon::setTestNow(Carbon::create(2026, 6, 4, 10, 0, 0));

        $sessionPayload = [
            'id' => '01kt7j6y6csksv9h5rj0hfpep4',
            'title' => 'Latihan Scoring Pagi',
            'bow_class' => 'recurve',
            'distance_category' => '20m',
            'distance_m' => 20,
            'num_ends' => 4,
            'arrows_per_end' => 3,
            'status' => ScoringSessionStatus::Completed->value,
            'ends' => [
                [
                    'end_number' => 1,
                    'arrows' => [
                        ['arrow_index' => 1, 'score_value' => 9],
                        ['arrow_index' => 2, 'score_value' => 10, 'is_x' => true],
                        ['arrow_index' => 3, 'score_value' => 8],
                    ],
                ],
                [
                    'end_number' => 2,
                    'arrows' => [
                        ['arrow_index' => 1, 'score_value' => 10],
                        ['arrow_index' => 2, 'score_value' => 9],
                        ['arrow_index' => 3, 'score_value' => 9],
                    ],
                ],
                [
                    'end_number' => 3,
                    'arrows' => [
                        ['arrow_index' => 1, 'score_value' => 8],
                        ['arrow_index' => 2, 'score_value' => 7],
                        ['arrow_index' => 3, 'score_value' => 0, 'is_miss' => true],
                    ],
                ],
                [
                    'end_number' => 4,
                    'arrows' => [
                        ['arrow_index' => 1, 'score_value' => 9],
                        ['arrow_index' => 2, 'score_value' => 8],
                        ['arrow_index' => 3, 'score_value' => 9],
                    ],
                ],
            ],
            'started_at' => Carbon::now()->toIso8601String(),
            'completed_at' => Carbon::now()->toIso8601String(),
        ];

        $this->postJson('/api/v1/scoring/sessions', $sessionPayload)->assertCreated();

        // 3. Verify XP was awarded:
        // Base 50 XP + (5 * 12 arrows = 60 XP) + 100 XP (Personal Best since it's the first session) = 210 XP
        $this->getJson('/api/v1/gamification/stats')
            ->assertOk()
            ->assertJsonPath('data.xp', 210)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.current_streak', 1)
            // Latihan Perdana should be unlocked (total sessions >= 1)
            ->assertJsonFragment([
                'id' => $this->sessionBadge->id,
                'name' => 'Latihan Perdana',
                'unlocked' => true,
            ])
            // Pemula Handal should remain locked (requires Level 2, i.e., 500 XP)
            ->assertJsonFragment([
                'id' => $this->levelBadge->id,
                'name' => 'Pemula Handal',
                'unlocked' => false,
            ]);

        // 4. Record another session next day to increase streak & cross level threshold
        Carbon::setTestNow(Carbon::create(2026, 6, 5, 10, 0, 0));

        // Submit 2nd session (60 arrows -> 5 * 60 = 300 XP + 50 XP base + 100 XP PB (new best score) = 450 XP)
        // Total XP becomes 210 + 450 = 660 XP (crosses 500 XP -> Level 2)
        $ends = [];
        for ($i = 1; $i <= 20; $i++) {
            $ends[] = [
                'end_number' => $i,
                'arrows' => [
                    ['arrow_index' => 1, 'score_value' => 10],
                    ['arrow_index' => 2, 'score_value' => 10],
                    ['arrow_index' => 3, 'score_value' => 10],
                ],
            ];
        }

        $sessionPayload2 = array_merge($sessionPayload, [
            'id' => '01kt7j6y6csksv9h5rj0hfpep5',
            'num_ends' => 20,
            'ends' => $ends,
            'started_at' => Carbon::now()->toIso8601String(),
            'completed_at' => Carbon::now()->toIso8601String(),
        ]);

        $this->postJson('/api/v1/scoring/sessions', $sessionPayload2)->assertCreated();

        // 5. Verify level up & second badge unlock
        $this->getJson('/api/v1/gamification/stats')
            ->assertOk()
            ->assertJsonPath('data.xp', 660)
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.current_streak', 2)
            // Pemula Handal should now be unlocked!
            ->assertJsonFragment([
                'id' => $this->levelBadge->id,
                'name' => 'Pemula Handal',
                'unlocked' => true,
            ]);

        // Clean up Carbon mock
        Carbon::setTestNow();
    }
}
