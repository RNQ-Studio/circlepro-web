<?php

namespace Tests\Feature\Api;

use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_guest_can_create_show_update_and_delete_quote(): void
    {
        $quoteId = $this->postJson('/api/v1/quotes', [
            'text' => 'Talk is cheap. Show me the code.',
            'author' => 'Linus Torvalds',
            'source' => 'LKML',
        ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Linus Torvalds')
            ->json('data.id');

        $this->getJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJsonPath('data.text', 'Talk is cheap. Show me the code.');

        $this->putJson("/api/v1/quotes/{$quoteId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.author', 'Linus Torvalds');

        $this->deleteJson("/api/v1/quotes/{$quoteId}")
            ->assertOk()
            ->assertJson(['message' => 'Quote deleted']);

        $this->assertSoftDeleted('quotes', ['id' => $quoteId]);
    }

    public function test_authenticated_user_can_love_and_unlove_quote(): void
    {
        $user = \App\Models\User::factory()->create();
        \Laravel\Passport\Passport::actingAs($user);

        $quote = Quote::factory()->create(['love_count' => 0]);

        // Love
        $this->postJson("/api/v1/quotes/{$quote->id}/love")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'loved' => true,
                    'love_count' => 1,
                ]
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
                ]
            ]);

        $this->assertDatabaseMissing('quote_loves', [
            'quote_id' => $quote->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(0, $quote->refresh()->love_count);
    }
}
