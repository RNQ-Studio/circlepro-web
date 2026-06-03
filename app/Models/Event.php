<?php

namespace App\Models;

use App\Support\Enums\EventFormat;
use App\Support\Enums\EventStatus;
use App\Support\Enums\EventTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $created_by
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $banner_url
 * @property EventTier $tier
 * @property EventFormat $format
 * @property EventStatus $status
 * @property string|null $province
 * @property string|null $city
 * @property string|null $venue_name
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property int|null $capacity
 * @property array|null $schedule
 * @property string|null $rules
 * @property bool $is_external
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read User $creator
 * @property-read Collection<int, EventDivision> $divisions
 */
class Event extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'id',
        'organization_id',
        'created_by',
        'title',
        'slug',
        'description',
        'banner_url',
        'tier',
        'format',
        'status',
        'province',
        'city',
        'venue_name',
        'address',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'registration_opens_at',
        'registration_closes_at',
        'capacity',
        'schedule',
        'rules',
        'is_external',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => EventTier::class,
            'format' => EventFormat::class,
            'status' => EventStatus::class,
            'schedule' => 'array',
            'is_external' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'published_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<EventDivision, $this> */
    public function divisions(): HasMany
    {
        return $this->hasMany(EventDivision::class)->orderBy('id');
    }

    /** @param Builder<Event> $query */
    public function scopeForOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }

    /** @param Builder<Event> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', '!=', EventStatus::Draft);
    }

    /**
     * Case-insensitive search by title, city, province, or venue.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'ilike', "%{$term}%")
                ->orWhere('city', 'ilike', "%{$term}%")
                ->orWhere('province', 'ilike', "%{$term}%")
                ->orWhere('venue_name', 'ilike', "%{$term}%");
        });
    }
}
