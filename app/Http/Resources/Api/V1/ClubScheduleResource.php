<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ClubSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClubSchedule
 */
class ClubScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $myAttendance = $this->relationLoaded('attendances')
            ? $this->attendances->first()
            : null;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time->toIso8601String(),
            'created_by' => $this->created_by,
            'my_attendance' => $myAttendance ? [
                'id' => $myAttendance->id,
                'status' => $myAttendance->status->value,
                'remark' => $myAttendance->remark,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
