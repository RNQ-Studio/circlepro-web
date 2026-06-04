<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\EventFormat;
use App\Support\Enums\EventTier;
use App\Support\Enums\Gender;
use App\Support\Enums\MemberRole;
use App\Support\Enums\OrganizationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_event_and_it_shows_in_list(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Create an organization of type event_organizer
        $org = Organization::factory()->create([
            'type' => OrganizationType::Club,
        ]);

        // Make user owner of organization to pass canManage checks
        OrganizationMember::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => MemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $payload = [
            'organization_id' => $org->id,
            'title' => 'Kejuaraan Nasional Panahan 2026',
            'description' => 'Kejuaraan bergengsi tingkat nasional.',
            'tier' => EventTier::S->value,
            'format' => EventFormat::RankingRound->value,
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'ends_at' => now()->addDays(12)->toIso8601String(),
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Pusat',
            'venue_name' => 'Stadion Utama Gelora Bung Karno',
            'divisions' => [
                [
                    'bow_class' => BowClass::Recurve->value,
                    'gender' => Gender::Male->value,
                    'age_group' => AgeGroup::Dewasa->value,
                    'distance_category' => DistanceCategory::D70m->value,
                    'distance_m' => 70,
                    'num_arrows' => 72,
                    'max_score' => 720,
                    'entry_fee' => 150000,
                    'capacity' => 100,
                ],
                [
                    'bow_class' => BowClass::Compound->value,
                    'gender' => Gender::Female->value,
                    'age_group' => AgeGroup::Dewasa->value,
                    'distance_category' => DistanceCategory::D50m->value,
                    'distance_m' => 50,
                    'num_arrows' => 72,
                    'max_score' => 720,
                    'entry_fee' => 150000,
                    'capacity' => 80,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/events', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', $payload['title'])
            ->assertJsonPath('data.organization_name', $org->name)
            ->assertJsonCount(2, 'data.divisions');

        $eventId = $response->json('data.id');

        // Check if event appears in list and filters
        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $eventId);

        // Filter by location
        $this->getJson('/api/v1/events?filter[province]=DKI Jakarta')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Search scope
        $this->getJson('/api/v1/events?filter[search]=Nasional')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_view_event_details(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(5),
        ]);

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', $event->title);
    }

    public function test_creator_can_update_event_and_divisions(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(5),
        ]);

        $division = $event->divisions()->create([
            'bow_class' => BowClass::Recurve->value,
            'gender' => Gender::Male->value,
            'age_group' => AgeGroup::Dewasa->value,
            'distance_category' => DistanceCategory::D70m->value,
            'distance_m' => 70,
            'num_arrows' => 72,
            'max_score' => 720,
            'entry_fee' => 100000,
        ]);

        $payload = [
            'title' => 'Updated Event Title',
            'divisions' => [
                [
                    'id' => $division->id,
                    'bow_class' => BowClass::Recurve->value,
                    'gender' => Gender::Male->value,
                    'age_group' => AgeGroup::Dewasa->value,
                    'distance_category' => DistanceCategory::D70m->value,
                    'distance_m' => 70,
                    'num_arrows' => 72,
                    'max_score' => 720,
                    // update fee
                    'entry_fee' => 125000,
                ],
                // add new division
                [
                    'bow_class' => BowClass::BarebowStandard->value,
                    'gender' => Gender::Male->value,
                    'age_group' => AgeGroup::Dewasa->value,
                    'distance_category' => DistanceCategory::D50m->value,
                    'distance_m' => 50,
                    'num_arrows' => 72,
                    'max_score' => 720,
                    'entry_fee' => 100000,
                ],
            ],
        ];

        $this->putJson("/api/v1/events/{$event->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Event Title')
            ->assertJsonCount(2, 'data.divisions')
            ->assertJsonPath('data.divisions.0.entry_fee', 125000);
    }

    public function test_unauthorized_user_cannot_update_or_delete_event(): void
    {
        $creator = User::factory()->create();
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $creator->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(5),
        ]);

        $stranger = User::factory()->create();
        Passport::actingAs($stranger);

        $this->putJson("/api/v1/events/{$event->id}", ['title' => 'Hacked'])
            ->assertForbidden();

        $this->deleteJson("/api/v1/events/{$event->id}")
            ->assertForbidden();
    }

    public function test_user_can_retrieve_their_created_events(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $org = Organization::factory()->create();
        Event::factory()->count(3)->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(5),
        ]);

        // another user event
        Event::factory()->create([
            'organization_id' => $org->id,
            'created_by' => User::factory()->create()->id,
            'tier' => EventTier::B,
            'format' => EventFormat::RankingRound,
            'starts_at' => now()->addDays(5),
        ]);

        $this->getJson('/api/v1/my-events')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
