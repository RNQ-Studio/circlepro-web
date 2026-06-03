<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $bio
 * @property array $specialties
 * @property string|null $certification
 * @property int $experience_years
 * @property float $hourly_rate
 * @property string|null $whatsapp_number
 * @property bool $is_verified
 * @property array|null $availability
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CoachProfile extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'bio',
        'specialties',
        'certification',
        'experience_years',
        'hourly_rate',
        'whatsapp_number',
        'is_verified',
        'availability',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'availability' => 'array',
            'is_verified' => 'boolean',
            'hourly_rate' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CoachReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(CoachReview::class);
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }
}
