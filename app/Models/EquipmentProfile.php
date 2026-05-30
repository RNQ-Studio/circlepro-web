<?php

namespace App\Models;

use App\Support\Enums\BowClass;
use Database\Factories\EquipmentProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property BowClass $bow_class
 * @property string|null $bow_model
 * @property float|null $draw_weight_lbs
 * @property string|null $arrow_spec
 * @property string|null $tuning_notes
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class EquipmentProfile extends Model
{
    /** @use HasFactory<EquipmentProfileFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'bow_class',
        'bow_model',
        'draw_weight_lbs',
        'arrow_spec',
        'tuning_notes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'bow_class' => BowClass::class,
            'draw_weight_lbs' => 'float',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ScoringSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ScoringSession::class);
    }
}
