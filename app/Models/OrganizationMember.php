<?php

namespace App\Models;

use App\Support\Enums\MemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property string|null $member_code
 * @property MemberRole $role
 * @property string $status
 * @property Carbon|null $joined_at
 * @property int|null $invited_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrganizationMember extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'member_code',
        'role',
        'status',
        'joined_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
