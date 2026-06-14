<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a group's title/round-format or transition its lifecycle
 * (finish/abandon) — Sprint 02, task 2.6. Host-only; a denied check returns 404
 * (privacy) before validation runs.
 */
class UpdateScoringSessionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        abort_unless(
            $group instanceof ScoringSessionGroup
                && ($this->user()?->can('manage', $group) ?? false),
            404,
            'Resource not found.',
        );

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'distance_category' => ['sometimes', Rule::enum(DistanceCategory::class)],
            'distance_m' => ['sometimes', 'integer', 'min:1', 'max:900'],
            'environment' => ['sometimes', Rule::enum(ArcheryEnvironment::class)],
            'target_face_cm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'target_face_id' => ['sometimes', 'nullable', 'string', 'exists:target_faces,id'],
            'num_ends' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'arrows_per_end' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'status' => ['sometimes', Rule::enum(ScoringSessionStatus::class)],
        ];
    }
}
