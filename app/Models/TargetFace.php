<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $organization_id
 * @property string $code
 * @property string $name
 * @property string|null $image_path
 * @property array $scoring_rules
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TargetFace extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'organization_id',
        'code',
        'name',
        'image_path',
        'scoring_rules',
        'total_participants',
    ];

    protected function casts(): array
    {
        return [
            'scoring_rules' => 'array',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Organization, $this>
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
