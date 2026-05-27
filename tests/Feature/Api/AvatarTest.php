<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('gcs');

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
            'filesystems.default' => 'public',
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['email' => 'avatar@example.com']);

        $this->accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'avatar@example.com',
            'password' => 'password',
        ])->json('data.access_token');
    }

    public function test_user_can_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file])
            ->assertOk()
            ->assertJsonPath('success', true);

        $avatarUrl = $response->json('data.avatar_url');
        $this->assertNotNull($avatarUrl);

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar);

        $asset = Asset::find($this->user->avatar);
        $this->assertNotNull($asset);
        Storage::disk('gcs')->assertExists($asset->path);
    }

    public function test_uploading_new_avatar_deletes_old_one(): void
    {
        $file1 = UploadedFile::fake()->image('first.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('second.png', 200, 200);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file1])
            ->assertOk();

        $this->user->refresh();
        $oldAvatarId = $this->user->avatar;
        $oldAsset = Asset::find($oldAvatarId);
        $this->assertNotNull($oldAsset);
        $oldPath = $oldAsset->path;

        Storage::disk('gcs')->assertExists($oldPath);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file2])
            ->assertOk();

        Storage::disk('gcs')->assertMissing($oldPath);
        $this->user->refresh();

        $newAsset = Asset::find($this->user->avatar);
        $this->assertNotNull($newAsset);
        Storage::disk('gcs')->assertExists($newAsset->path);
    }

    public function test_avatar_url_appears_in_me_response(): void
    {
        $file = UploadedFile::fake()->image('me.jpg');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file])
            ->assertOk();

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar_url']]);
    }

    public function test_avatar_upload_rejects_non_image_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_rejects_oversized_file(): void
    {
        // 3MB > 2MB limit
        $file = UploadedFile::fake()->image('big.jpg')->size(3000);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/avatar', ['avatar' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->postJson('/api/v1/auth/avatar', ['avatar' => $file])
            ->assertUnauthorized();
    }

    public function test_me_returns_null_avatar_url_when_no_avatar(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);
    }
}
