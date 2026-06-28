<?php

namespace App\Models;

use App\Support\Enums\GroupScorerAssignmentType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $scoring_session_group_id
 * @property int $user_id
 * @property int|null $assigned_by_user_id
 * @property int $target_butt
 * @property GroupScorerAssignmentType $assignment_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScoringSessionGroup $group
 * @property-read User $user
 * @property-read User|null $assignedBy
 */
class GroupScorer extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'scoring_session_group_id',
        'user_id',
        'assigned_by_user_id',
        'target_butt',
        'assignment_type',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => GroupScorerAssignmentType::class,
        ];
    }

    /** @return BelongsTo<ScoringSessionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ScoringSessionGroup::class, 'scoring_session_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
