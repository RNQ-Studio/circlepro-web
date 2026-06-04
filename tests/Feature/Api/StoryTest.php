<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Story;
use App\Models\User;
use App\Services\AssetUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('gcs');
    }

    public function test_user_can_upload_story_and_list_stories(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 1. Upload story untuk user 1
        Passport::actingAs($user1);
        $file = UploadedFile::fake()->image('story1.jpg');

        $responseUpload = $this->postJson('/api/v1/stories', [
            'file' => $file,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'media_type', 'media_url', 'expires_at', 'created_at'],
            ]);

        $storyId = $responseUpload->json('data.id');

        $this->assertDatabaseHas('stories', [
            'id' => $storyId,
            'user_id' => $user1->id,
            'media_type' => 'image',
        ]);

        $story = Story::find($storyId);
        $this->assertNotNull($story->asset_id);
        Storage::disk('gcs')->assertExists($story->asset->path);

        // 2. Upload story untuk user 2
        Passport::actingAs($user2);
        $videoFile = UploadedFile::fake()->create('story2.mp4', 1000, 'video/mp4');

        $this->postJson('/api/v1/stories', [
            'file' => $videoFile,
        ])->assertCreated();

        $this->assertDatabaseHas('stories', [
            'user_id' => $user2->id,
            'media_type' => 'video',
        ]);

        // 3. List active stories
        Passport::actingAs($user1);
        $responseList = $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Kita punya 2 user dengan story aktif
        $responseList->assertJsonCount(2, 'data');
        $responseList->assertJsonStructure([
            'data' => [
                '*' => [
                    'user' => ['id', 'full_name', 'username', 'avatar_url'],
                    'stories' => [
                        '*' => ['id', 'media_type', 'media_url', 'expires_at', 'created_at'],
                    ],
                ],
            ],
        ]);
    }

    public function test_expired_stories_are_not_listed(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Story 1: Masih aktif (expiring in 23 hours)
        $story1 = Story::query()->create([
            'user_id' => $user->id,
            'media_type' => 'image',
            'media_url' => 'https://mock.gcs/story1.jpg',
            'expires_at' => now()->addHours(23),
        ]);

        // Story 2: Sudah kedaluwarsa (expired 1 hour ago)
        $story2 = Story::query()->create([
            'user_id' => $user->id,
            'media_type' => 'image',
            'media_url' => 'https://mock.gcs/story2.jpg',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/stories')
            ->assertOk();

        // Hanya story1 yang dikembalikan
        $response->assertJsonCount(1, 'data');
        $response->assertJsonCount(1, 'data.0.stories');
        $this->assertSame($story1->id, $response->json('data.0.stories.0.id'));
    }

    public function test_user_can_delete_own_story(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        $file = UploadedFile::fake()->image('my_story.jpg');
        $storyId = $this->postJson('/api/v1/stories', ['file' => $file])
            ->json('data.id');

        $story = Story::find($storyId);
        $asset = $story->asset;
        Storage::disk('gcs')->assertExists($asset->path);

        // User lain mencoba menghapus -> forbidden
        $otherUser = User::factory()->create();
        Passport::actingAs($otherUser);
        $this->deleteJson("/api/v1/stories/{$storyId}")->assertForbidden();

        // Pemilik menghapus -> ok
        Passport::actingAs($user);
        $this->deleteJson("/api/v1/stories/{$storyId}")->assertOk();

        $this->assertDatabaseMissing('stories', ['id' => $storyId]);

        // Status asset harus hard_deleted & file di GCS harus terhapus
        $asset->refresh();
        $this->assertSame('hard_deleted', $asset->status->value);
        Storage::disk('gcs')->assertMissing($asset->path);
    }

    public function test_cleanup_command_removes_expired_stories_and_gcs_files(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);

        // Buat asset & story yang kedaluwarsa
        $file = UploadedFile::fake()->image('expired_story.jpg');
        $asset = app(AssetUploadService::class)->upload(
            file: $file,
            type: 'story',
            userId: $user->id,
            retainUntil: now()->subMinutes(5)
        );

        $story = Story::query()->create([
            'id' => Str::ulid(),
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'media_type' => 'image',
            'media_url' => $asset->getPublicUrl(),
            'expires_at' => now()->subMinutes(5),
        ]);

        Storage::disk('gcs')->assertExists($asset->path);

        // Jalankan command cleanup
        $this->artisan('stories:clean-expired')
            ->assertExitCode(0);

        // Story harus terhapus
        $this->assertDatabaseMissing('stories', ['id' => $story->id]);

        // Asset harus di-hard delete
        $asset->refresh();
        $this->assertSame('hard_deleted', $asset->status->value);
        Storage::disk('gcs')->assertMissing($asset->path);
    }
}
