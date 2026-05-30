<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-category notification preferences (Module 8, task 2.5 partial).
 */
class NotificationPreferenceController extends Controller
{
    /** Known notification categories. */
    public const CATEGORIES = ['rating', 'event', 'social', 'market', 'marketing'];

    public function index(Request $request): JsonResponse
    {
        $saved = NotificationPreference::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('category');

        $prefs = collect(self::CATEGORIES)->map(fn (string $cat): array => [
            'category' => $cat,
            'push_enabled' => $saved[$cat]->push_enabled ?? true,
            'email_enabled' => $saved[$cat]->email_enabled ?? false,
        ])->values();

        return ApiResponse::success($prefs);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', Rule::in(self::CATEGORIES)],
            'preferences.*.push_enabled' => ['required', 'boolean'],
            'preferences.*.email_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $pref) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'category' => $pref['category']],
                ['push_enabled' => $pref['push_enabled'], 'email_enabled' => $pref['email_enabled']],
            );
        }

        return $this->index($request);
    }
}
