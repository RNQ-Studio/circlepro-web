<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Support\Enums\SystemRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $full_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar
 * @property bool $is_active
 * @property SystemRole $system_role
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $last_active_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, EventRegistration> $registrations
 * @property-read Collection<int, Story> $stories
 */
#[Fillable(['name', 'username', 'full_name', 'email', 'password', 'is_active', 'system_role', 'avatar', 'phone', 'phone_verified_at', 'last_active_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    protected string $guard_name = 'web';

    /** Roles allowed to access the back-office panel. */
    public const PANEL_ROLES = ['super-admin', 'admin', 'staff'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(self::PANEL_ROLES);
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function avatarAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'avatar');
    }

    // ─── ManahPro relationships (Module 0 & 1) ──────────────────────

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** @return HasOne<CoachProfile, $this> */
    public function coachProfile(): HasOne
    {
        return $this->hasOne(CoachProfile::class);
    }

    /** @return HasOne<UserStat, $this> */
    public function stats(): HasOne
    {
        return $this->hasOne(UserStat::class);
    }

    /** @return BelongsToMany<Badge, $this> */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this, Follow> */
    public function followings(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followee_id')->using(Follow::class)->withPivot('created_at');
    }

    /** @return BelongsToMany<User, $this, Follow> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followee_id', 'follower_id')->using(Follow::class)->withPivot('created_at');
    }

    /** @return HasOne<UserSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /** @return HasMany<UserAuthProvider, $this> */
    public function authProviders(): HasMany
    {
        return $this->hasMany(UserAuthProvider::class);
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /** @return HasMany<EquipmentProfile, $this> */
    public function equipmentProfiles(): HasMany
    {
        return $this->hasMany(EquipmentProfile::class);
    }

    /** @return HasMany<ScoringSession, $this> */
    public function scoringSessions(): HasMany
    {
        return $this->hasMany(ScoringSession::class);
    }

    /** @return HasMany<PersonalBest, $this> */
    public function personalBests(): HasMany
    {
        return $this->hasMany(PersonalBest::class);
    }

    /** @return HasMany<Story, $this> */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'system_role' => SystemRole::class,
        ];
    }

    /**
     * Find the user instance for the given username.
     */
    public function findForPassport(string $username): ?User
    {
        return $this->where('email', $username)
            ->orWhere('phone', $username)
            ->first();
    }

    /**
     * Validate the password for the Passport password grant.
     */
    public function validateForPassportPasswordGrant(#[\SensitiveParameter] string $password): bool
    {
        $cacheKey = 'otp_login_token_'.$this->getKey();
        $cachedToken = cache($cacheKey);

        if ($cachedToken !== null && hash_equals($cachedToken, $password)) {
            cache()->forget($cacheKey);

            return true;
        }

        return Hash::check($password, $this->password);
    }
}
