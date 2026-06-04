<?php

namespace App\Support;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\Enums\SyncSource;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for a scoring-session payload (with nested
 * ends/arrows), reused by the store, update and batch-sync requests.
 */
class ScoringSessionRules
{
    /**
     * @param  string  $prefix  e.g. '' for a single session, 'sessions.*.' for a batch
     * @param  int|null  $userId  scope equipment_profile_id ownership when provided
     * @param  bool  $partial  when true (update), core fields use "sometimes"
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = '', ?int $userId = null, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        $equipmentExists = Rule::exists('equipment_profiles', 'id');
        if ($userId !== null) {
            $equipmentExists->where('user_id', $userId);
        }

        return [
            "{$prefix}id" => ['nullable', 'ulid'],
            "{$prefix}client_uuid" => ['nullable', 'uuid'],
            "{$prefix}equipment_profile_id" => ['nullable', 'ulid', $equipmentExists],
            "{$prefix}organization_id" => ['nullable', 'ulid', Rule::exists('organizations', 'id')],
            "{$prefix}scoring_session_group_id" => ['nullable', 'ulid'],
            "{$prefix}title" => ['nullable', 'string', 'max:120'],
            "{$prefix}bow_class" => [$req, Rule::enum(BowClass::class)],
            "{$prefix}distance_category" => [$req, Rule::enum(DistanceCategory::class)],
            "{$prefix}distance_m" => [$req, 'integer', 'min:1', 'max:200'],
            "{$prefix}environment" => ['nullable', Rule::enum(ArcheryEnvironment::class)],
            "{$prefix}target_face_cm" => ['nullable', 'integer', 'min:1', 'max:200'],
            "{$prefix}target_face_id" => ['nullable', 'ulid', Rule::exists('target_faces', 'id')],
            "{$prefix}num_ends" => [$req, 'integer', 'min:1', 'max:60'],
            "{$prefix}arrows_per_end" => [$req, 'integer', 'min:1', 'max:12'],
            "{$prefix}status" => ['nullable', Rule::enum(ScoringSessionStatus::class)],
            "{$prefix}notes" => ['nullable', 'string', 'max:2000'],
            "{$prefix}started_at" => [$partial ? 'sometimes' : 'required', 'date'],
            "{$prefix}completed_at" => ['nullable', 'date'],
            "{$prefix}source" => ['nullable', Rule::enum(SyncSource::class)],

            "{$prefix}ends" => ['nullable', 'array'],
            "{$prefix}ends.*.id" => ['nullable', 'ulid'],
            "{$prefix}ends.*.end_number" => ['required_with:'."{$prefix}ends", 'integer', 'min:1'],
            "{$prefix}ends.*.arrows" => ['nullable', 'array'],
            "{$prefix}ends.*.arrows.*.id" => ['nullable', 'ulid'],
            "{$prefix}ends.*.arrows.*.arrow_index" => ['required', 'integer', 'min:0'],
            "{$prefix}ends.*.arrows.*.score_value" => ['required', 'integer', 'min:0', 'max:10'],
            "{$prefix}ends.*.arrows.*.is_x" => ['boolean'],
            "{$prefix}ends.*.arrows.*.is_miss" => ['boolean'],
            "{$prefix}ends.*.arrows.*.pos_x" => ['nullable', 'numeric'],
            "{$prefix}ends.*.arrows.*.pos_y" => ['nullable', 'numeric'],
        ];
    }
}
