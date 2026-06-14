<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ClubAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClubAttendance
 */
class ClubAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_schedule_id' => $this->club_schedule_id,
            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user->full_name ?? $this->user->name,
                'username' => $this->user?->username,
                'avatar_url' => $this->user?->profile?->avatar_url,
            ],
            'status' => $this->status->value,
            'remark' => $this->remark,
            'marked_by' => $this->marked_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
