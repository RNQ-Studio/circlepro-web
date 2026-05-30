<?php

namespace App\Models;

use App\Support\Enums\AuthProvider as AuthProviderEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property AuthProviderEnum $provider
 * @property string $provider_uid
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserAuthProvider extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_uid',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AuthProviderEnum::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
