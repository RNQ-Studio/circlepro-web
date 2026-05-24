<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Support\Enums\AssetStatus;
use App\Support\Enums\StorageType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'id' => $uuid,
            'user_id' => null,
            'morphable_type' => 'user',
            'morphable_id' => 1,
            'storage_type' => StorageType::Gcs,
            'path' => "production/user/2026/05/{$uuid}.jpg",
            'url' => "https://storage.googleapis.com/bucket/production/user/2026/05/{$uuid}.jpg",
            'original_filename' => 'photo.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 5_000_000),
            'checksum' => hash('sha256', $uuid),
            'category' => 'user',
            'metadata' => ['width' => 800, 'height' => 600],
            'retain_until' => null,
            'is_protected' => false,
            'status' => AssetStatus::Active,
        ];
    }

    /** File yang sudah melewati masa retensi (kandidat soft delete). */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'retain_until' => now()->subDay(),
            'status' => AssetStatus::Active,
        ]);
    }

    /** File yang sudah di-soft-delete & jadwal hard delete-nya telah lewat. */
    public function pendingHardDelete(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AssetStatus::SoftDeleted,
            'soft_deleted_at' => now()->subDays(31),
            'scheduled_hard_delete_at' => now()->subDay(),
        ]);
    }

    /** File yang dilindungi dari penghapusan otomatis. */
    public function protected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_protected' => true,
        ]);
    }

    /** File permanen (tanpa kebijakan retensi). */
    public function permanent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'retain_until' => null,
        ]);
    }
}
