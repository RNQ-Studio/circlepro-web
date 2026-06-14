<?php

namespace Tests\Feature\Api;

use App\Models\Post;
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

    public function test_create_post_with_media_and_poll_and_vote(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $postData = [
            'body' => 'Post with media and poll',
            'media' => [
                ['url' => 'https://example.com/image1.jpg', 'type' => 'image', 'position' => 1],
                ['url' => 'https://example.com/video1.mp4', 'type' => 'video', 'position' => 2],
            ],
            'poll' => [
                'question' => 'What is your favorite bow type?',
                'options' => ['Recurve', 'Compound', 'Barebow'],
                'expires_at' => now()->addDays(2)->toIso8601String(),
            ],
        ];

        $response = $this->postJson('/api/v1/posts', $postData)
            ->assertCreated()
            ->assertJsonPath('data.body', 'Post with media and poll')
            ->assertJsonCount(2, 'data.media')
            ->assertJsonPath('data.media.0.url', 'https://example.com/image1.jpg')
            ->assertJsonPath('data.media.1.type', 'video')
            ->assertJsonPath('data.poll.question', 'What is your favorite bow type?')
            ->assertJsonCount(3, 'data.poll.options')
            ->assertJsonPath('data.poll.total_votes', 0)
            ->assertJsonPath('data.poll.user_voted_option_id', null);

        $pollId = $response->json('data.poll.id');
        $optionId = $response->json('data.poll.options.0.id');

        // Cast vote
        $this->postJson("/api/v1/polls/{$pollId}/vote", ['poll_option_id' => $optionId])
            ->assertOk()
            ->assertJsonPath('data.poll.total_votes', 1)
            ->assertJsonPath('data.poll.user_voted_option_id', $optionId)
            ->assertJsonPath('data.poll.options.0.votes_count', 1);
    }

    public function test_engagement_weighted_sorting(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Post 1: 0 likes, 0 comments (score = 0)
        $post1 = Post::factory()->create(['author_id' => $user->id, 'like_count' => 0, 'comment_count' => 0, 'created_at' => now()->subMinutes(10)]);
        // Post 2: 5 likes, 2 comments (score = 20)
        $post2 = Post::factory()->create(['author_id' => $user->id, 'like_count' => 5, 'comment_count' => 2, 'created_at' => now()->subMinutes(5)]);
        // Post 3: 2 likes, 0 comments (score = 4)
        $post3 = Post::factory()->create(['author_id' => $user->id, 'like_count' => 2, 'comment_count' => 0, 'created_at' => now()->subMinutes(2)]);

        $response = $this->getJson('/api/v1/posts?sort=engagement')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertEquals([$post2->id, $post3->id, $post1->id], array_slice($ids, 0, 3));
    }
}
