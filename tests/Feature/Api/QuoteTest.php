<?php

namespace Tests\Feature\Api;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_quotes(): void
    {
        Quote::factory()->count(3)->create();

        $this->getJson('/api/v1/quotes')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_guest_can_search_filter_sort_and_paginate_quotes(): void
    {
        Quote::factory()->create(['text' => 'Stay hungry, stay foolish.', 'author' => 'Steve Jobs', 'is_active' => true]);
        Quote::factory()->create(['text' => 'Imagination is more important.', 'author' => 'Albert Einstein', 'is_active' => true]);
        Quote::factory()->create(['text' => 'Old inactive quote.', 'author' => 'Anonymous', 'is_active' => false]);

        $this->getJson('/api/v1/quotes?filter[search]=einstein&filter[is_active]=1&sort=author&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.author', 'Albert Einstein')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_guest_can_sort_quotes_randomly_with_seed(): void
    {
        // Create 10 active quotes
        Quote::factory()->count(10)->create(['is_active' => true]);

        // Get random sorting with seed 123
        $response1 = $this->getJson('/api/v1/quotes?sort=random&seed=123&per_page=10')
            ->assertOk();

        $ids1 = collect($response1->json('data'))->pluck('id')->all();

        // Get random sorting with same seed 123
        $response2 = $this->getJson('/api/v1/quotes?sort=random&seed=123&per_page=10')
            ->assertOk();

        $ids2 = collect($response2->json('data'))->pluck('id')->all();

        // They must be identical because of the same seed
        $this->assertEquals($ids1, $ids2);

        // Get random sorting with different seed 456
        $response3 = $this->getJson('/api/v1/quotes?sort=random&seed=456&per_page=10')
            ->assertOk();

        $ids3 = collect($response3->json('data'))->pluck('id')->all();

        // They are highly likely to be different for different seeds
        $this->assertNotEquals($ids1, $ids3);
    }

    public function test_guest_can_show_a_single_quote(): void
    {
        $quote = Quote::factory()->create([
            'text' => 'Talk is cheap. Show me the code.',
            'author' => 'Linus Torvalds',
        ]);

        $this->getJson("/api/v1/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('data.text', 'Talk is cheap. Show me the code.')
            ->assertJsonPath('data.author', 'Linus Torvalds');
    }

    /**
     * The mobile quote API is intentionally read-only (commit 335f54a):
     * quotes are authored via the Filament admin panel, not the public API.
     * This guards against accidentally re-exposing write endpoints.
     */
    public function test_quote_write_endpoints_are_not_exposed(): void
    {
        $quote = Quote::factory()->create();

        $this->postJson('/api/v1/quotes', [
            'text' => 'Should not be creatable via API.',
            'author' => 'Nobody',
        ])->assertStatus(405);

        $this->putJson("/api/v1/quotes/{$quote->id}", ['is_active' => false])
            ->assertStatus(405);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")
            ->assertStatus(405);
    }

    public function test_authenticated_user_can_love_and_unlove_quote(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $quote = Quote::factory()->create(['love_count' => 0]);

        // Love
        $this->postJson("/api/v1/quotes/{$quote->id}/love")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'loved' => true,
                    'love_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('quote_loves', [
            'quote_id' => $quote->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, $quote->refresh()->love_count);

        // Unlove
        $this->deleteJson("/api/v1/quotes/{$quote->id}/love")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'loved' => false,
                    'love_count' => 0,
                ],
            ]);

        $this->assertDatabaseMissing('quote_loves', [
            'quote_id' => $quote->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(0, $quote->refresh()->love_count);
    }
}
