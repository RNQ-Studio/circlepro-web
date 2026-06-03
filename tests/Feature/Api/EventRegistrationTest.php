<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
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
use App\Support\Enums\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;
    private User $athlete;
    private Event $event;
    private EventDivision $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create();
        $this->athlete = User::factory()->create();

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
    }

    public function test_user_can_register_for_event_division_successfully(): void
    {
        Passport::actingAs($this->athlete);

        $payload = [
            'event_division_id' => $this->division->id,
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/register", $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', RegistrationStatus::Confirmed->value)
            ->assertJsonPath('data.user_id', $this->athlete->id);

        $this->assertDatabaseHas('event_registrations', [
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $this->assertEquals(1, $this->division->fresh()->num_participants);
    }

    public function test_user_cannot_register_for_same_division_twice(): void
    {
        Passport::actingAs($this->athlete);

        EventRegistration::factory()->create([
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $payload = [
            'event_division_id' => $this->division->id,
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/register", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda sudah terdaftar di divisi ini.');
    }

    public function test_user_cannot_register_if_division_capacity_full(): void
    {
        Passport::actingAs($this->athlete);

        // Fill capacity
        $this->division->update(['num_participants' => 10]);

        $payload = [
            'event_division_id' => $this->division->id,
        ];

        $this->postJson("/api/v1/events/{$this->event->id}/register", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kuota divisi ini sudah penuh.');
    }

    public function test_user_can_retrieve_their_own_tickets(): void
    {
        Passport::actingAs($this->athlete);

        EventRegistration::factory()->create([
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
        ]);

        $this->getJson('/api/v1/my-tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $this->athlete->id);
    }

    public function test_organizer_can_retrieve_event_participants(): void
    {
        Passport::actingAs($this->organizer);

        EventRegistration::factory()->create([
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
        ]);

        $this->getJson("/api/v1/events/{$this->event->id}/participants")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_organizer_cannot_retrieve_event_participants(): void
    {
        Passport::actingAs($this->athlete);

        $this->getJson("/api/v1/events/{$this->event->id}/participants")
            ->assertForbidden();
    }

    public function test_organizer_can_check_in_participant(): void
    {
        Passport::actingAs($this->organizer);

        $registration = EventRegistration::factory()->create([
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $this->postJson("/api/v1/registrations/{$registration->id}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', RegistrationStatus::CheckedIn->value);

        $this->assertDatabaseHas('event_registrations', [
            'id' => $registration->id,
            'status' => RegistrationStatus::CheckedIn->value,
        ]);
        $this->assertNotNull($registration->fresh()->checked_in_at);
    }

    public function test_organizer_can_update_participant_status(): void
    {
        Passport::actingAs($this->organizer);

        $registration = EventRegistration::factory()->create([
            'user_id' => $this->athlete->id,
            'event_division_id' => $this->division->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        $this->division->update(['num_participants' => 1]);

        $this->putJson("/api/v1/registrations/{$registration->id}/status", [
            'status' => RegistrationStatus::Cancelled->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RegistrationStatus::Cancelled->value);

        $this->assertEquals(0, $this->division->fresh()->num_participants);
    }
}
