<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FollowSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another_user(): void
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();

        Passport::actingAs($follower);

        $this->postJson("/api/v1/users/{$followee->id}/follow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil mengikuti pengguna.',
            ]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $follower->id,
            'followee_id' => $followee->id,
        ]);
    }

    public function test_following_is_idempotent(): void
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();

        Passport::actingAs($follower);

        // First follow
        $this->postJson("/api/v1/users/{$followee->id}/follow")->assertOk();

        // Second follow
        $this->postJson("/api/v1/users/{$followee->id}/follow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Anda sudah mengikuti pengguna ini.',
            ]);
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $this->postJson("/api/v1/users/{$user->id}/follow")
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak dapat mengikuti diri sendiri.',
            ]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $user->id,
            'followee_id' => $user->id,
        ]);
    }

    public function test_user_can_unfollow_user(): void
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();

        // Establish follow
        $follower->followings()->attach($followee->id);

        Passport::actingAs($follower);

        $this->postJson("/api/v1/users/{$followee->id}/unfollow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil batal mengikuti pengguna.',
            ]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $follower->id,
            'followee_id' => $followee->id,
        ]);
    }

    public function test_unfollowing_not_followed_user(): void
    {
        $follower = User::factory()->create();
        $followee = User::factory()->create();

        Passport::actingAs($follower);

        $this->postJson("/api/v1/users/{$followee->id}/unfollow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Anda tidak mengikuti pengguna ini.',
            ]);
    }

    public function test_can_list_followers_and_following(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // user1 followed by user2 and user3
        $user2->followings()->attach($user1->id);
        $user3->followings()->attach($user1->id);

        // user1 follows user3
        $user1->followings()->attach($user3->id);

        Passport::actingAs($user1);

        // Get followers of user1
        $this->getJson("/api/v1/users/{$user1->id}/followers")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $user2->id)
            ->assertJsonPath('data.1.id', $user3->id);

        // Get following of user1
        $this->getJson("/api/v1/users/{$user1->id}/following")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user3->id);
    }

    public function test_feed_filtering_following_only(): void
    {
        $user = User::factory()->create();
        $followed = User::factory()->create();
        $unfollowed = User::factory()->create();

        // user follows followed
        $user->followings()->attach($followed->id);

        Passport::actingAs($user);

        // Create posts with distinct timestamps for deterministic sorting
        $postSelf = Post::factory()->create([
            'author_id' => $user->id,
            'body' => 'Self Post',
            'created_at' => now()->subMinutes(10),
        ]);
        $postFollowed = Post::factory()->create([
            'author_id' => $followed->id,
            'body' => 'Followed Post',
            'created_at' => now(),
        ]);
        $postUnfollowed = Post::factory()->create([
            'author_id' => $unfollowed->id,
            'body' => 'Unfollowed Post',
            'created_at' => now()->subMinutes(20),
        ]);

        // Default feed (global/all)
        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Following feed
        $this->getJson('/api/v1/posts?feed=following')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Followed Post') // sorted by -created_at usually, or vice versa depending on factory timestamps
            ->assertJsonMissing(['body' => 'Unfollowed Post']);
    }
}
