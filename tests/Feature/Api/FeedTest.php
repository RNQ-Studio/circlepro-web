<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_list_like_and_comment_post(): void
    {
        Passport::actingAs(User::factory()->create());

        $postId = $this->postJson('/api/v1/posts', ['body' => 'Latihan pagi ini mantap!'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Latihan pagi ini mantap!')
            ->json('data.id');

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $postId);

        // Like is idempotent.
        $this->postJson("/api/v1/posts/{$postId}/like")
            ->assertOk()
            ->assertJsonPath('data.like_count', 1);
        $this->postJson("/api/v1/posts/{$postId}/like")
            ->assertOk()
            ->assertJsonPath('data.like_count', 1);
        $this->deleteJson("/api/v1/posts/{$postId}/like")
            ->assertOk()
            ->assertJsonPath('data.like_count', 0);

        $this->postJson("/api/v1/posts/{$postId}/comments", ['body' => 'Keren!'])
            ->assertCreated();
        $this->getJson("/api/v1/posts/{$postId}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Keren!');

        $this->assertDatabaseHas('posts', ['id' => $postId, 'comment_count' => 1]);
    }

    public function test_share_own_scoring_session_to_feed(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $session = ScoringSession::factory()->completed()->create([
            'user_id' => $user->id,
            'total_score' => 320,
            'bow_class' => 'recurve',
        ]);

        $this->postJson('/api/v1/posts', [
            'body' => 'Skor baru!',
            'shared_type' => 'scoring_session',
            'shared_id' => $session->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.shared.total_score', 320)
            ->assertJsonPath('data.shared.type', 'scoring_session');
    }

    public function test_cannot_share_another_users_session(): void
    {
        $other = User::factory()->create();
        $session = ScoringSession::factory()->completed()->create(['user_id' => $other->id]);

        Passport::actingAs(User::factory()->create());
        $this->postJson('/api/v1/posts', [
            'body' => 'Bukan punyaku',
            'shared_type' => 'scoring_session',
            'shared_id' => $session->id,
        ])->assertForbidden();
    }

    public function test_only_author_can_delete_post(): void
    {
        $author = User::factory()->create();
        Passport::actingAs($author);
        $postId = $this->postJson('/api/v1/posts', ['body' => 'Milikku'])->json('data.id');

        Passport::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/posts/{$postId}")->assertForbidden();

        Passport::actingAs($author);
        $this->deleteJson("/api/v1/posts/{$postId}")->assertOk();
    }
}
