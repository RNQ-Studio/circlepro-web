<?php

namespace App\Models;

use App\Support\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property OrganizationType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $logo_url
 * @property string|null $banner_url
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $province
 * @property string|null $city
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_verified
 * @property bool $is_active
 * @property Carbon|null $founded_at
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Organization extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'slug',
        'description',
        'logo_url',
        'banner_url',
        'email',
        'phone',
        'province',
        'city',
        'address',
        'latitude',
        'longitude',
        'is_verified',
        'is_active',
        'founded_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'founded_at' => 'date',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Organization, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /** @param Builder<Organization> $query */
    public function scopeOfType(Builder $query, OrganizationType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * Case-insensitive search by name or city (PostgreSQL ILIKE).
     *
     * @param  Builder<Organization>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('city', 'ilike', "%{$term}%");
        });
    }

    /**
     * The singleton platform organization (root tenant, national/global scope).
     */
    public static function platform(): ?self
    {
        return static::query()->where('type', OrganizationType::Platform->value)->first();
    }
}
