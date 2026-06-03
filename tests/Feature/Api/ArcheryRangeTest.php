<?php

namespace Tests\Feature\Api;

use App\Models\ArcheryRange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ArcheryRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_manage_archery_ranges(): void
    {
        Passport::actingAs($this->user);

        // 1. Create Range
        $response = $this->postJson('/api/v1/ranges', [
            'name' => 'Sasana Panahan Surabaya',
            'description' => 'Lapangan indoor ber-AC',
            'address' => 'Jl. Manyar Kertoarjo No. 10',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'latitude' => -7.2759,
            'longitude' => 112.7756,
            'facilities' => ['toilet', 'canteen', 'parking'],
            'phone' => '0812345678',
            'price_per_hour' => 50000,
            'image_url' => 'https://example.com/range.jpg',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Sasana Panahan Surabaya');
        $rangeId = $response->json('data.id');

        // 2. Update Range
        $this->putJson("/api/v1/ranges/{$rangeId}", [
            'name' => 'Sasana Panahan Surabaya (Updated)',
            'price_per_hour' => 60000,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sasana Panahan Surabaya (Updated)')
            ->assertJsonPath('data.price_per_hour', 60000);

        // 3. Show Range
        $this->getJson("/api/v1/ranges/{$rangeId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Sasana Panahan Surabaya (Updated)');

        // 4. Delete Range
        $this->deleteJson("/api/v1/ranges/{$rangeId}")
            ->assertOk();

        $this->getJson("/api/v1/ranges/{$rangeId}")
            ->assertNotFound();
    }

    public function test_user_can_list_search_and_calculate_distance(): void
    {
        Passport::actingAs($this->user);

        // Create 2 ranges
        // Surabaya Range
        $sbyRange = ArcheryRange::query()->create([
            'name' => 'Sasana Panahan Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'latitude' => -7.2759,
            'longitude' => 112.7756,
            'facilities' => ['toilet', 'parking'],
            'price_per_hour' => 50000,
        ]);

        // Malang Range
        $mlgRange = ArcheryRange::query()->create([
            'name' => 'Sasana Panahan Malang',
            'city' => 'Malang',
            'province' => 'Jawa Timur',
            'latitude' => -7.9818,
            'longitude' => 112.6265,
            'facilities' => ['canteen', 'parking'],
            'price_per_hour' => 45000,
        ]);

        // 1. List all
        $this->getJson('/api/v1/ranges')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // 2. Search
        $this->getJson('/api/v1/ranges?filter[search]=Malang')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mlgRange->id);

        // 3. Facility filter
        $this->getJson('/api/v1/ranges?filter[facility]=canteen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mlgRange->id);

        // 4. Distance Calculation & Sorting
        // Let's search from Malang coordinates (-7.98, 112.62)
        // Malang range should be very close, Surabaya range should be far (~80-90 km)
        $response = $this->getJson('/api/v1/ranges?latitude=-7.98&longitude=112.62')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.id', $mlgRange->id); // Malang first because closer
        $response->assertJsonPath('data.1.id', $sbyRange->id); // Surabaya second

        // Distance field should be populated
        $this->assertLessThan(5, $response->json('data.0.distance')); // Malang is < 5km from -7.98, 112.62
        $this->assertGreaterThan(70, $response->json('data.1.distance')); // Surabaya is > 70km from Malang
    }
}
