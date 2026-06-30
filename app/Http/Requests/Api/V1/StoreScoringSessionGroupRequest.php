<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a group scoring session (Sprint 02, task 2.1). Any authenticated user
 * may host. organization_id stays out of Phase 0 — it is switched on in
 * Sprint 28 (club layer).
 */
class StoreScoringSessionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'distance_category' => ['required', Rule::enum(DistanceCategory::class)],
            'distance_m' => ['required', 'integer', 'min:1', 'max:900'],
            'environment' => ['sometimes', Rule::enum(ArcheryEnvironment::class)],
            'target_face_cm' => ['nullable', 'integer', 'min:1', 'max:200'],
            'target_face_id' => ['nullable', 'string', 'exists:target_faces,id'],
            'num_ends' => ['required', 'integer', 'min:1', 'max:60'],
            'arrows_per_end' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'sighter_end_count' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'round_preset_key' => ['nullable', 'string', 'max:64'],
            'round_preset_label' => ['nullable', 'string', 'max:80'],
            // "host ikut menembak": host also joins the roster as an owned row.
            'host_participates' => ['sometimes', 'boolean'],
            'host_bow_class' => ['nullable', Rule::enum(BowClass::class)],
            'started_at' => ['sometimes', 'date'],
        ];
    }
}
