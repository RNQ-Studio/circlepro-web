<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $icon_code
 * @property string $requirement_type
 * @property int $requirement_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Badge extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'description',
        'icon_code',
        'requirement_type',
        'requirement_value',
    ];

    protected function casts(): array
    {
        return [
            'requirement_value' => 'integer',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
