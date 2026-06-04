<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'ad_campaign_id',
        'placement',
        'image_url',
        'title',
        'body',
        'click_url',
        'impression_count',
        'click_count',
    ];

    protected function casts(): array
    {
        return [
            'impression_count' => 'integer',
            'click_count' => 'integer',
        ];
    }

    /** @return BelongsTo<AdCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
