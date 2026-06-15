<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Auto-distribute participants across bantalan in one call — Sprint 16,
 * task 16.3. Host-only roster setup (§4 matrix): the host picks how many butts
 * the field has and, optionally, the seats per butt (capacity, default 4 = the
 * usual A–D). A denied check returns 404 (privacy) before validation.
 */
class AutoDistributeButtsRequest extends FormRequest
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
            'butt_count' => ['required', 'integer', 'min:1', 'max:60'],
            // Seats per butt (A–D by default); capped at 26 so the round-robin
            // seat letter never leaves A–Z.
            'capacity' => ['nullable', 'integer', 'min:1', 'max:26'],
        ];
    }
}
