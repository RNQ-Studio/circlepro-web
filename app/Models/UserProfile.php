<?php

namespace App\Models;

use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\Gender;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $avatar_url
 * @property string|null $banner_url
 * @property string|null $bio
 * @property Gender|null $gender
 * @property Carbon|null $birth_date
 * @property AgeGroup|null $age_group
 * @property string|null $province
 * @property string|null $city
 * @property BowClass|null $primary_bow_class
 * @property string|null $home_club_id
 * @property array<string, mixed>|null $social_links
 * @property string|null $peak_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserProfile extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'avatar_url',
        'banner_url',
        'bio',
        'gender',
        'birth_date',
        'age_group',
        'province',
        'city',
        'primary_bow_class',
        'home_club_id',
        'social_links',
        'peak_title',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'age_group' => AgeGroup::class,
            'primary_bow_class' => BowClass::class,
            'birth_date' => 'date',
            'social_links' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function homeClub(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'home_club_id');
    }
}
