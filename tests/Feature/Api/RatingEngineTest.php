<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Rating;
use App\Models\RatingBand;
use App\Models\RatingHistory;
use App\Models\RatingPeriod;
use App\Models\ScoringSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\RatingEngine;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\EventFormat;
use App\Support\Enums\EventTier;
use App\Support\Enums\Gender;
use App\Support\Enums\MemberRole;
use App\Support\Enums\RatingPeriodStatus;
use App\Support\Enums\RatingStatus;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RatingEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Organization $org;

    private Event $event;

    private EventDivision $division;

    private RatingEngine $ratingEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ratingEngine = $this->app->make(RatingEngine::class);

        $this->organizer = User::factory()->create();
        $this->org = Organization::factory()->create(['slug' => 'manahpro']);

        OrganizationMember::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->organizer->id,
            'role' => MemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->event = Event::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->organizer->id,
            'tier' => EventTier::D,
            'format' => EventFormat::RankingRound,
            'starts_at' => now(),
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
    }

    public function test_rating_computation_on_division_finalize(): void
    {
        Passport::actingAs($this->organizer);

        // Setup 5 participants to meet Tier D minimum threshold (5)
        $athletes = User::factory()->count(5)->create();
        $scores = [680, 670, 650, 620, 600];

        foreach ($athletes as $idx => $athlete) {
            // Profile details (province and city)
            UserProfile::create([
                'user_id' => $athlete->id,
                'province' => 'Jawa Barat',
                'city' => $idx % 2 === 0 ? 'Bandung' : 'Bogor',
            ]);

            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => $scores[$idx],
                'max_possible_score' => 720,
                'x_count' => 10 - $idx,
                'ten_count' => 15 - $idx,
                'miss_count' => $idx,
            ]);
        }

        // Call finalization endpoint
        $this->postJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/finalize-ratings")
            ->assertOk()
            ->assertJsonPath('message', 'Rating berhasil dihitung dan difinalisasi.');

        // Verify division status updated to rated and SoF set
        $this->division->refresh();
        $this->assertEquals('rated', $this->division->rating_status);
        $this->assertEquals(1500.0, $this->division->sof_avg_rating);

        // Check ratings table entries
        foreach ($athletes as $idx => $athlete) {
            $this->assertDatabaseHas('ratings', [
                'user_id' => $athlete->id,
                'organization_id' => $this->org->id,
                'bow_class' => BowClass::Recurve->value,
                'gender' => Gender::Male->value,
                'age_group' => AgeGroup::Dewasa->value,
                'distance_category' => DistanceCategory::D70m->value,
                'events_count' => 1,
            ]);

            // Retrieve rating to check Glicko-2 dynamics
            $rating = Rating::where('user_id', $athlete->id)->first();
            $this->assertNotNull($rating);

            // Check rating history entry exists
            $this->assertDatabaseHas('rating_history', [
                'rating_id' => $rating->id,
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'score_achieved' => $scores[$idx],
                'placement' => $idx + 1,
            ]);
        }

        // Assert relative rating ordering: Athlete 0 (680 score) should have higher mu than Athlete 2 (650), who has higher mu than Athlete 4 (600)
        $rating0 = Rating::where('user_id', $athletes[0]->id)->first();
        $rating1 = Rating::where('user_id', $athletes[2]->id)->first();
        $rating2 = Rating::where('user_id', $athletes[4]->id)->first();

        $this->assertTrue($rating0->mu > $rating1->mu);
        $this->assertTrue($rating1->mu > $rating2->mu);
    }

    public function test_leaderboard_query_filters_and_sorting(): void
    {
        // Seed rating bands
        RatingBand::create([
            'title' => 'Expert Archer',
            'badge' => 'Expert',
            'color' => 'gold',
            'min_display_rating' => 1200,
            'max_display_rating' => 1600,
            'sort_order' => 1,
        ]);

        RatingBand::create([
            'title' => 'Novice Archer',
            'badge' => 'Novice',
            'color' => 'bronze',
            'min_display_rating' => 600,
            'max_display_rating' => 1199,
            'sort_order' => 2,
        ]);

        $users = User::factory()->count(4)->create();

        // Profiles
        UserProfile::create(['user_id' => $users[0]->id, 'province' => 'Jawa Barat', 'city' => 'Bandung']);
        UserProfile::create(['user_id' => $users[1]->id, 'province' => 'Jawa Barat', 'city' => 'Bogor']);
        UserProfile::create(['user_id' => $users[2]->id, 'province' => 'DKI Jakarta', 'city' => 'Jakarta Selatan']);
        UserProfile::create(['user_id' => $users[3]->id, 'province' => 'Jawa Barat', 'city' => 'Bandung']);

        // Ratings: users 0, 1, 2 are Recurve, user 3 is Compound
        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $users[0]->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1700.0,
            'phi' => 100.0,
            'display_rating' => 1500.0, // Expert Band
            'status' => RatingStatus::Established->value,
            'events_count' => 10,
        ]);

        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $users[1]->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1400.0,
            'phi' => 100.0,
            'display_rating' => 1200.0, // Expert Band
            'status' => RatingStatus::Ranked->value,
            'events_count' => 5,
        ]);

        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $users[2]->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1100.0,
            'phi' => 100.0,
            'display_rating' => 900.0, // Novice Band
            'status' => RatingStatus::Provisional->value,
            'events_count' => 2,
        ]);

        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $users[3]->id,
            'bow_class' => BowClass::Compound->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1600.0,
            'phi' => 50.0,
            'display_rating' => 1500.0,
            'status' => RatingStatus::Ranked->value,
            'events_count' => 4,
        ]);

        // 1. Check complete leaderboard sorting (Recurve, Male, Dewasa, 70m)
        $response = $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Check sort order (1500 -> 1200 -> 900)
        $response->assertJsonPath('data.0.user_id', $users[0]->id)
            ->assertJsonPath('data.0.title', 'Expert Archer')
            ->assertJsonPath('data.1.user_id', $users[1]->id)
            ->assertJsonPath('data.2.user_id', $users[2]->id)
            ->assertJsonPath('data.2.title', 'Novice Archer');

        // 2. Check Province Filter (Jawa Barat)
        $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'province' => 'Jawa Barat',
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user_id', $users[0]->id)
            ->assertJsonPath('data.1.user_id', $users[1]->id);

        // 3. Check City Filter (Bandung)
        $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $users[0]->id);
    }

    public function test_user_ratings_and_history_endpoints(): void
    {
        $athlete = User::factory()->create();

        $rating = Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $athlete->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1600.0,
            'phi' => 100.0,
            'display_rating' => 1400.0,
            'status' => RatingStatus::Ranked->value,
            'events_count' => 4,
        ]);

        $period = RatingPeriod::create([
            'organization_id' => $this->org->id,
            'period_month' => now()->startOfMonth(),
            'status' => RatingPeriodStatus::Open,
        ]);

        RatingHistory::create([
            'rating_id' => $rating->id,
            'user_id' => $athlete->id,
            'event_division_id' => $this->division->id,
            'rating_period_id' => $period->id,
            'mu_before' => 1500.0,
            'mu_after' => 1600.0,
            'phi_before' => 150.0,
            'phi_after' => 100.0,
            'sigma_before' => 0.06,
            'sigma_after' => 0.06,
            'display_before' => 1200.0,
            'display_after' => 1400.0,
            'score_achieved' => 670,
            'nps' => 930.5,
            'placement' => 2,
            'num_participants' => 10,
            'event_tier' => EventTier::B,
            'k_effective' => 32.0,
            'computed_at' => now(),
        ]);

        // Get user ratings list (public)
        $this->getJson("/api/v1/users/{$athlete->id}/ratings")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rating->id);

        // Get rating history list (public)
        $this->getJson("/api/v1/users/{$athlete->id}/ratings/{$rating->id}/history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score_achieved', 670)
            ->assertJsonPath('data.0.display_change', 200);

        // Get my ratings (authenticated)
        Passport::actingAs($athlete);
        $this->getJson('/api/v1/my-ratings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rating->id);
    }

    public function test_monthly_decay(): void
    {
        $athlete = User::factory()->create();

        $rating = Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $athlete->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1600.0,
            'phi' => 100.0,
            'display_rating' => 1400.0,
            'status' => RatingStatus::Established->value,
            'events_count' => 12,
            'last_event_date' => now()->subMonths(3)->toDateString(),
        ]);

        // Execute monthly decay for current date
        $this->ratingEngine->applyMonthlyDecay($this->org, now());

        // Refresh rating and verify decay applied
        $rating->refresh();

        $this->assertEquals(1600.0, $rating->mu); // internal mu remains same
        $this->assertTrue($rating->phi > 100.0); // uncertainty increased
        $this->assertTrue($rating->display_rating < 1400.0); // display rating decreased

        // Check rating status is still established (since inactivity is only 3 months, not >= 12 months)
        $this->assertEquals(RatingStatus::Established->value, $rating->status->value);

        // Verify history entry for decay was created
        $this->assertDatabaseHas('rating_history', [
            'rating_id' => $rating->id,
            'user_id' => $athlete->id,
            'event_division_id' => null, // null indicates decay/manual update
        ]);
    }

    public function test_minimum_participant_threshold_and_skipping(): void
    {
        Passport::actingAs($this->organizer);

        // Create an event with Tier B
        $event = Event::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->organizer->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now(),
        ]);

        $division = $event->divisions()->create([
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

        // Register only 4 participants (less than 5, which is Tier D minimum)
        $athletes = User::factory()->count(4)->create();
        foreach ($athletes as $athlete) {
            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => 600,
                'max_possible_score' => 720,
            ]);
        }

        // Finalize ratings - should skip calculation because n < 5
        $this->postJson("/api/v1/events/{$event->id}/divisions/{$division->id}/finalize-ratings")
            ->assertOk();

        $division->refresh();
        $this->assertEquals('rated', $division->rating_status);

        // Verify no ratings were updated or history created
        $this->assertDatabaseEmpty('ratings');
        $this->assertDatabaseEmpty('rating_history');
    }

    public function test_minimum_participant_threshold_and_downgrading(): void
    {
        Passport::actingAs($this->organizer);

        // Create an event with Tier S
        $event = Event::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->organizer->id,
            'tier' => EventTier::S,
            'format' => EventFormat::RankingRound,
            'starts_at' => now(),
        ]);

        $division = $event->divisions()->create([
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

        // Register 6 participants (should downgrade S -> D because 6 < 10)
        $athletes = User::factory()->count(6)->create();
        foreach ($athletes as $idx => $athlete) {
            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => 600 + ($idx * 10),
                'max_possible_score' => 720,
            ]);
        }

        $this->postJson("/api/v1/events/{$event->id}/divisions/{$division->id}/finalize-ratings")
            ->assertOk();

        // Check that history entry has Tier S (from event) but rating changes were calculated with effective Tier D
        $this->assertDatabaseHas('rating_history', [
            'event_tier' => 'S',
            'num_participants' => 6,
        ]);
        $this->assertDatabaseCount('ratings', 6);
    }

    public function test_strength_of_field_adjustment(): void
    {
        Passport::actingAs($this->organizer);

        // Setup high-rated opponents (SoF > 1600), registering 5 to pass Tier D threshold
        $athletes = User::factory()->count(5)->create();
        $scores = [680, 670, 650, 620, 600];

        foreach ($athletes as $idx => $athlete) {
            Rating::create([
                'organization_id' => $this->org->id,
                'user_id' => $athlete->id,
                'bow_class' => BowClass::Recurve->value,
                'gender' => Gender::Male->value,
                'age_group' => AgeGroup::Dewasa->value,
                'distance_category' => DistanceCategory::D70m->value,
                'mu' => 1700.0, // Avg mu = 1700 > 1600
                'phi' => 100.0,
                'sigma' => 0.06,
                'display_rating' => 1500.0,
                'status' => RatingStatus::Ranked->value,
                'events_count' => 5,
            ]);

            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => $scores[$idx],
                'max_possible_score' => 720,
            ]);
        }

        $this->postJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/finalize-ratings")
            ->assertOk();

        // Verify division's SoF is logged correctly (> 1600)
        $this->division->refresh();
        $this->assertEquals(1700.0, $this->division->sof_avg_rating);
    }

    public function test_sandbagging_and_anomaly_detection(): void
    {
        Passport::actingAs($this->organizer);

        // Register 5 participants to satisfy Tier D threshold
        $athletes = User::factory()->count(5)->create();

        // Athlete 0 has pre-existing rating but will perform excessively well
        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $athletes[0]->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 500.0,
            'phi' => 50.0,
            'display_rating' => 400.0,
            'status' => RatingStatus::Established->value,
            'events_count' => 10,
        ]);

        foreach ($athletes as $idx => $athlete) {
            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $this->division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => $idx === 0 ? 710 : 500 - ($idx * 50), // Athlete 0 gets massive score
                'max_possible_score' => 720,
            ]);
        }

        $this->postJson("/api/v1/events/{$this->event->id}/divisions/{$this->division->id}/finalize-ratings")
            ->assertOk();

        // Verify Athlete 0 gets flagged as suspicious
        $this->assertDatabaseHas('ratings', [
            'user_id' => $athletes[0]->id,
            'is_suspicious' => true,
        ]);

        $this->assertDatabaseHas('rating_history', [
            'user_id' => $athletes[0]->id,
            'is_suspicious' => true,
        ]);
    }

    public function test_calibration_mode_event(): void
    {
        Passport::actingAs($this->organizer);

        // Create a calibration event with Tier D to pass min participants (5)
        $calibrationEvent = Event::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->organizer->id,
            'tier' => EventTier::D,
            'format' => EventFormat::RankingRound,
            'starts_at' => now(),
            'is_calibration' => true,
        ]);

        $division = $calibrationEvent->divisions()->create([
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

        // Register 5 participants
        $athletes = User::factory()->count(5)->create();
        $scores = [680, 670, 650, 620, 600];

        foreach ($athletes as $idx => $athlete) {
            // Seed initial ratings in DB
            Rating::create([
                'organization_id' => $this->org->id,
                'user_id' => $athlete->id,
                'bow_class' => BowClass::Recurve->value,
                'gender' => Gender::Male->value,
                'age_group' => AgeGroup::Dewasa->value,
                'distance_category' => DistanceCategory::D70m->value,
                'mu' => 1500.0,
                'phi' => 100.0,
                'display_rating' => 1300.0,
                'status' => RatingStatus::Ranked->value,
                'events_count' => 5,
            ]);

            EventRegistration::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => 'checked_in',
            ]);

            ScoringSession::factory()->create([
                'user_id' => $athlete->id,
                'event_division_id' => $division->id,
                'status' => ScoringSessionStatus::Completed,
                'total_score' => $scores[$idx],
                'max_possible_score' => 720,
            ]);
        }

        $this->postJson("/api/v1/events/{$calibrationEvent->id}/divisions/{$division->id}/finalize-ratings")
            ->assertOk();

        // Verify history has is_calibration = true
        $this->assertDatabaseHas('rating_history', [
            'is_calibration' => true,
            'event_division_id' => $division->id,
        ]);

        // Verify ratings table remains unmodified (mu remains 1500.0 instead of updating)
        foreach ($athletes as $athlete) {
            $this->assertDatabaseHas('ratings', [
                'user_id' => $athlete->id,
                'mu' => 1500.0,
            ]);
        }
    }

    public function test_global_silent_mode(): void
    {
        // Seed some rating
        $athlete = User::factory()->create();
        Rating::create([
            'organization_id' => $this->org->id,
            'user_id' => $athlete->id,
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'mu' => 1500.0,
            'phi' => 100.0,
            'display_rating' => 1300.0,
            'status' => RatingStatus::Ranked->value,
            'events_count' => 5,
        ]);

        // 1. Regular behavior (silent mode false)
        config(['app.rating_silent_mode' => false]);

        $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 2. Silent mode active (unauthenticated/guest)
        config(['app.rating_silent_mode' => true]);

        $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
        ]))
            ->assertStatus(403)
            ->assertJsonPath('message', 'Sistem rating sedang dalam masa kalibrasi.');

        // 3. Silent mode active (admin user allowed)
        Passport::actingAs($this->organizer);
        $this->getJson('/api/v1/leaderboard?'.http_build_query([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
