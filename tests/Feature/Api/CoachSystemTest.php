<?php

namespace Tests\Feature\Api;

use App\Models\CoachProfile;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CoachSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $coachUser;

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coachUser = User::factory()->create();
        $this->coachUser->update(['full_name' => $this->coachUser->name]);
        UserProfile::query()->create([
            'user_id' => $this->coachUser->id,
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
        ]);

        $this->clientUser = User::factory()->create();
        $this->clientUser->update(['full_name' => $this->clientUser->name]);
        UserProfile::query()->create([
            'user_id' => $this->clientUser->id,
            'city' => 'Malang',
            'province' => 'Jawa Timur',
        ]);
    }

    public function test_user_can_register_and_update_coach_profile(): void
    {
        Passport::actingAs($this->coachUser);

        // 1. Register as coach
        $response = $this->postJson('/api/v1/coaches', [
            'bio' => 'Pelatih panahan berpengalaman 5 tahun.',
            'specialties' => ['recurve', 'standard_bow'],
            'certification' => 'Sertifikat Nasional Level 1',
            'experience_years' => 5,
            'hourly_rate' => 150000,
            'whatsapp_number' => '081234567890',
            'availability' => ['Sabtu Pagi', 'Minggu Sore'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.certification', 'Sertifikat Nasional Level 1');
        $coachId = $response->json('data.id');

        // 2. Prevent duplicate registration
        $this->postJson('/api/v1/coaches', [
            'bio' => 'Duplicate bio',
            'specialties' => ['compound'],
            'experience_years' => 2,
            'hourly_rate' => 100000,
        ])->assertStatus(422);

        // 3. Update coach profile
        $this->putJson("/api/v1/coaches/{$coachId}", [
            'bio' => 'Bio update',
            'specialties' => ['recurve', 'compound'],
            'certification' => 'Sertifikat Nasional Level 2',
            'experience_years' => 6,
            'hourly_rate' => 200000,
            'whatsapp_number' => '081234567891',
        ])
            ->assertOk()
            ->assertJsonPath('data.certification', 'Sertifikat Nasional Level 2')
            ->assertJsonPath('data.hourly_rate', 200000);

        // 4. Unauthorized update
        Passport::actingAs($this->clientUser);
        $this->putJson("/api/v1/coaches/{$coachId}", [
            'bio' => 'Hacked bio',
            'specialties' => ['recurve'],
            'experience_years' => 10,
            'hourly_rate' => 500000,
        ])->assertForbidden();
    }

    public function test_user_can_list_and_search_coaches(): void
    {
        // Setup coach directly in DB
        $coach = CoachProfile::query()->create([
            'user_id' => $this->coachUser->id,
            'bio' => 'Coach Surabaya',
            'specialties' => ['recurve'],
            'certification' => 'National',
            'experience_years' => 3,
            'hourly_rate' => 100000,
            'whatsapp_number' => '081',
            'is_verified' => true,
        ]);

        Passport::actingAs($this->clientUser);

        // 1. Fetch listing
        $response = $this->getJson('/api/v1/coaches')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $coach->id);

        // 2. Filter by specialty
        $this->getJson('/api/v1/coaches?filter[specialty]=recurve')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/coaches?filter[specialty]=compound')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 3. Filter by city
        $this->getJson('/api/v1/coaches?filter[city]=Surabaya')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/coaches?filter[city]=Jakarta')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // 4. Search by name
        $this->getJson("/api/v1/coaches?filter[search]={$this->coachUser->name}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_review_coaches(): void
    {
        $coach = CoachProfile::query()->create([
            'user_id' => $this->coachUser->id,
            'bio' => 'Coach Surabaya',
            'specialties' => ['recurve'],
            'certification' => 'National',
            'experience_years' => 3,
            'hourly_rate' => 100000,
            'is_verified' => true,
        ]);

        // 1. Client reviews coach
        Passport::actingAs($this->clientUser);
        $this->postJson("/api/v1/coaches/{$coach->id}/reviews", [
            'rating' => 5,
            'comment' => 'Sangat sabar mengajar pemula.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        // 2. Client updates review (idempotent / unique constraint)
        $this->postJson("/api/v1/coaches/{$coach->id}/reviews", [
            'rating' => 4,
            'comment' => 'Ubah ulasan ke bintang 4.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseCount('coach_reviews', 1);

        // 3. Coach attempts to review self
        Passport::actingAs($this->coachUser);
        $this->postJson("/api/v1/coaches/{$coach->id}/reviews", [
            'rating' => 5,
            'comment' => 'Review diri sendiri',
        ])->assertStatus(422);

        // 4. Fetch coach details shows avg rating & count
        Passport::actingAs($this->clientUser);
        $this->getJson("/api/v1/coaches/{$coach->id}")
            ->assertOk()
            ->assertJsonPath('data.average_rating', 4)
            ->assertJsonPath('data.reviews_count', 1);

        // 5. Fetch review list
        $this->getJson("/api/v1/coaches/{$coach->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Ubah ulasan ke bintang 4.');
    }
}
