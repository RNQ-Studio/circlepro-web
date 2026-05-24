<?php

namespace Tests\Feature\Console;

use App\Models\Asset;
use App\Support\Enums\AssetStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_command_marks_expired_assets(): void
    {
        $expired = Asset::factory()->expired()->create();

        $this->artisan('assets:soft-delete-expired')->assertSuccessful();

        $expired->refresh();
        $this->assertSame(AssetStatus::SoftDeleted, $expired->status);
        $this->assertNotNull($expired->soft_deleted_at);
        $this->assertNotNull($expired->scheduled_hard_delete_at);
        // Default 30 hari ke depan.
        $this->assertTrue($expired->scheduled_hard_delete_at->greaterThan(now()->addDays(29)));
    }

    public function test_soft_delete_command_skips_protected_assets(): void
    {
        $protected = Asset::factory()->expired()->protected()->create();

        $this->artisan('assets:soft-delete-expired')->assertSuccessful();

        $protected->refresh();
        $this->assertSame(AssetStatus::Active, $protected->status);
    }

    public function test_soft_delete_command_skips_permanent_assets(): void
    {
        // retain_until NULL → permanen, tidak boleh tersentuh.
        $permanent = Asset::factory()->permanent()->create();

        $this->artisan('assets:soft-delete-expired')->assertSuccessful();

        $permanent->refresh();
        $this->assertSame(AssetStatus::Active, $permanent->status);
    }

    public function test_hard_delete_command_removes_file_and_marks_record(): void
    {
        Storage::fake('gcs');

        $asset = Asset::factory()->pendingHardDelete()->create();
        Storage::disk('gcs')->put($asset->path, 'dummy-content');

        $this->artisan('assets:hard-delete-expired')->assertSuccessful();

        $asset->refresh();
        $this->assertSame(AssetStatus::HardDeleted, $asset->status);
        $this->assertNotNull($asset->hard_deleted_at);
        Storage::disk('gcs')->assertMissing($asset->path);
    }

    public function test_hard_delete_command_is_idempotent_when_file_missing(): void
    {
        Storage::fake('gcs');

        // File tidak pernah dibuat di disk → command tetap menandai hard_deleted.
        $asset = Asset::factory()->pendingHardDelete()->create();

        $this->artisan('assets:hard-delete-expired')->assertSuccessful();

        $asset->refresh();
        $this->assertSame(AssetStatus::HardDeleted, $asset->status);
    }

    public function test_hard_delete_command_ignores_active_assets(): void
    {
        Storage::fake('gcs');

        $active = Asset::factory()->expired()->create(); // status masih active

        $this->artisan('assets:hard-delete-expired')->assertSuccessful();

        $active->refresh();
        $this->assertSame(AssetStatus::Active, $active->status);
    }
}
