<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringSessionClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One guest-slot claim. For the host inbox (task 13.2) it carries rich context
 * — the slot's score, when it was shot, the display name — so the host decides
 * from memory, not a guess. The `slot` block is only present when the session
 * is eager-loaded.
 *
 * @mixin ScoringSessionClaim
 */
class ScoringSessionClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->scoring_session_group_id,
            'session_id' => $this->scoring_session_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'message' => $this->message,
            'claimant' => [
                'id' => $this->claimant_user_id,
                'name' => $this->claimant->name,
            ],
            'resolved_by_user_id' => $this->resolved_by_user_id,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Rich slot context for the host inbox (13.2).
            'slot' => $this->whenLoaded('session', fn (): array => [
                'session_id' => $this->session->id,
                'display_name' => $this->session->guest_name ?? $this->session->user?->name,
                'started_at' => $this->session->started_at->toIso8601String(),
                'distance_category' => $this->session->distance_category?->value,
                'distance_m' => $this->session->distance_m,
                'target_face_cm' => $this->session->target_face_cm,
                'target_butt' => $this->session->target_butt,
                'status' => $this->session->status?->value,
                'total_score' => $this->session->total_score,
                'arrows_shot' => $this->session->arrows_shot,
                'x_count' => $this->session->x_count,
                'ten_count' => $this->session->ten_count,
                // After approval this flips for the new owner (honest PB, 13.4).
                'is_personal_best' => $this->session->is_personal_best,
                'is_guest' => $this->session->isGuest(),
            ]),
        ];
    }
}
