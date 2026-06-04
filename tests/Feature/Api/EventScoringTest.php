<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\ScoringSession;
use App\Models\User;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\EventFormat;
use App\Support\Enums\EventTier;
use App\Support\Enums\Gender;
use App\Support\Enums\MemberRole;
use App\Support\Enums\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EventScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $athleteA;

    private User $athleteB;

    private Event $event;

    private EventDivision $division;

    private EventRegistration $regA;

    private EventRegistration $regB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create();
        $this->athleteA = User::factory()->create();
        $this->athleteB = User::factory()->create();

        $org = Organization::factory()->create();
        OrganizationMember::query()->create([
            'organization_id' => $org->id,
            'user_id' => $this->organizer->id,
            'role' => MemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->event = Event::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $this->organizer->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(10),
        ]);

        $this->division = $this->event->divisions()->create([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'distance_m' => 70,
            'num_arrows' => 72,
            'max_score' => 720,
            'entry_fee' => 150000,
            'capacity' => 10,
        ]);

        $this->regA = EventRegistration::factory()->create([
            'user_id' => $this->athleteA->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $this->regB = EventRegistration::factory()->create([
            'user_id' => $this->athleteB->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);
    }

    public function test_organizer_can_assign_targets_successfully(): void
    {
        Passport::actingAs($this->organizer);

        $payload = [
            'assignments' => [
                [
                    'registration_id' => $this->regA->id,
                    'target_butt' => 5,
                    'target_letter' => 'A',
                ],
                [
                    'registration_id' => $this->regB->id,
                    'target_butt' => 5,
                    'target_letter' => 'B',
                ],
            ],
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/assign-targets", $payload)
            ->assertOk();

        $this->assertDatabaseHas('event_registrations', [
            'id' => $this->regA->id,
            'target_butt' => 5,
            'target_letter' => 'A',
        ]);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $this->regB->id,
            'target_butt' => 5,
            'target_letter' => 'B',
        ]);
    }

    public function test_organizer_cannot_assign_colliding_targets(): void
    {
        Passport::actingAs($this->organizer);

        // Put A on 5A
        $this->regA->update(['target_butt' => 5, 'target_letter' => 'A']);

        // Attempt to assign B on 5A as well
        $payload = [
            'assignments' => [
                [
                    'registration_id' => $this->regB->id,
                    'target_butt' => 5,
                    'target_letter' => 'A',
                ],
            ],
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/assign-targets", $payload)
            ->assertStatus(500); // Unique constraint violation throws 500
    }

    public function test_can_get_target_scorecard_and_initializes_scoring_sessions(): void
    {
        Passport::actingAs($this->organizer);

        // Assign target
        $this->regA->update(['target_butt' => 5, 'target_letter' => 'A']);

        $response = $this->getJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/targets/5/scorecard")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.registration_id', $this->regA->id)
            ->assertJsonPath('data.0.target_letter', 'A');

        // Check if scoring session was created automatically
        $this->assertDatabaseHas('scoring_sessions', [
            'user_id' => $this->athleteA->id,
            'event_division_id' => $this->division->id,
        ]);
    }

    public function test_organizer_can_save_end_scores_and_calculates_aggregates(): void
    {
        Passport::actingAs($this->organizer);

        // Assign A to 5A, B to 5B
        $this->regA->update(['target_butt' => 5, 'target_letter' => 'A']);
        $this->regB->update(['target_butt' => 5, 'target_letter' => 'B']);

        // Load scorecard once to initialize sessions
        $this->getJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/targets/5/scorecard")
            ->assertOk();

        $payload = [
            'scores' => [
                [
                    'user_id' => $this->athleteA->id,
                    'arrows' => [
                        ['score_value' => 10, 'is_x' => true, 'is_miss' => false],
                        ['score_value' => 10, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 9, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 9, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 8, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 0, 'is_x' => false, 'is_miss' => true], // Miss
                    ],
                ],
                [
                    'user_id' => $this->athleteB->id,
                    'arrows' => [
                        ['score_value' => 9, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 9, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 9, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 8, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 8, 'is_x' => false, 'is_miss' => false],
                        ['score_value' => 8, 'is_x' => false, 'is_miss' => false],
                    ],
                ],
            ],
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/targets/5/ends/1", $payload)
            ->assertOk();

        // Verify athlete A aggregates: total score = 46, X = 1, 10 = 2, Miss = 1, average = 46/6 = 7.67
        $sessionA = ScoringSession::where('user_id', $this->athleteA->id)
            ->where('event_division_id', $this->division->id)
            ->first();

        $this->assertEquals(46, $sessionA->total_score);
        $this->assertEquals(1, $sessionA->x_count);
        $this->assertEquals(2, $sessionA->ten_count);
        $this->assertEquals(1, $sessionA->miss_count);
        $this->assertEquals(6, $sessionA->arrows_shot);
        $this->assertEquals(7.67, round($sessionA->avg_per_arrow, 2));

        // Verify athlete B aggregates: total score = 51, X = 0, 10 = 0, Miss = 0, average = 51/6 = 8.5
        $sessionB = ScoringSession::where('user_id', $this->athleteB->id)
            ->where('event_division_id', $this->division->id)
            ->first();

        $this->assertEquals(51, $sessionB->total_score);
        $this->assertEquals(0, $sessionB->x_count);
        $this->assertEquals(0, $sessionB->ten_count);
        $this->assertEquals(0, $sessionB->miss_count);
        $this->assertEquals(51 / 6, $sessionB->avg_per_arrow);
    }

    public function test_can_get_leaderboard_sorted_correctly(): void
    {
        Passport::actingAs($this->organizer);

        // Assign targets
        $this->regA->update(['target_butt' => 5, 'target_letter' => 'A', 'bib_number' => 'RE-01']);
        $this->regB->update(['target_butt' => 5, 'target_letter' => 'B', 'bib_number' => 'RE-02']);

        // Initialize and save score session for A (Total score: 46)
        $sessionA = ScoringSession::factory()->create([
            'user_id' => $this->athleteA->id,
            'event_division_id' => $this->division->id,
            'total_score' => 46,
            'x_count' => 1,
            'ten_count' => 2,
            'miss_count' => 0,
        ]);

        // Initialize and save score session for B (Total score: 51)
        $sessionB = ScoringSession::factory()->create([
            'user_id' => $this->athleteB->id,
            'event_division_id' => $this->division->id,
            'total_score' => 51,
            'x_count' => 0,
            'ten_count' => 0,
            'miss_count' => 0,
        ]);

        // Query Leaderboard: B should be 1st (total score 51), A should be 2nd (total score 46)
        $this->getJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/leaderboard")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user_id', $this->athleteB->id)
            ->assertJsonPath('data.1.user_id', $this->athleteA->id);
    }
}
