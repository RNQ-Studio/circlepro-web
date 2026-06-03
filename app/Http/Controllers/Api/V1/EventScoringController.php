<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\ScoringArrow;
use App\Models\ScoringEnd;
use App\Models\ScoringSession;
use App\Services\EventService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventScoringController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    /** Assign target butts and letters to participants (organizer only). */
    public function assignTargets(Request $request, Event $event): JsonResponse
    {
        if (!$this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to assign targets.', 403);
        }

        $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.registration_id' => ['required', 'string', 'exists:event_registrations,id'],
            'assignments.*.target_butt' => ['required', 'integer', 'min:1'],
            'assignments.*.target_letter' => ['required', 'string', 'in:A,B,C,D,E,F'],
        ]);

        DB::transaction(function () use ($request): void {
            foreach ($request->input('assignments') as $assign) {
                $registration = EventRegistration::query()->findOrFail($assign['registration_id']);
                
                // Update target assignments
                $registration->update([
                    'target_butt' => $assign['target_butt'],
                    'target_letter' => strtoupper($assign['target_letter']),
                ]);
            }
        });

        return ApiResponse::success(null, 'Target pembagian berhasil diperbarui.');
    }

    /** Get scorecards for all athletes on a specific target butt. */
    public function getTargetScorecard(Event $event, EventDivision $division, int $target_butt): JsonResponse
    {
        // Get all participants for this target
        $registrations = EventRegistration::query()
            ->where('event_division_id', $division->id)
            ->where('target_butt', $target_butt)
            ->with('user.avatarAsset')
            ->get();

        $response = [];
        foreach ($registrations as $reg) {
            // Find or initialize competitive session for this athlete
            $session = ScoringSession::query()->firstOrCreate([
                'user_id' => $reg->user_id,
                'event_division_id' => $division->id,
            ], [
                'bow_class' => $division->bow_class->value,
                'distance_category' => $division->distance_category->value,
                'distance_m' => $division->distance_m,
                'num_ends' => max(1, $division->num_arrows / 6),
                'arrows_per_end' => 6,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            $session->load('ends.arrows');

            $response[] = [
                'registration_id' => $reg->id,
                'bib_number' => $reg->bib_number,
                'target_butt' => $reg->target_butt,
                'target_letter' => $reg->target_letter,
                'user' => [
                    'id' => $reg->user->id,
                    'name' => $reg->user->name,
                    'avatar_url' => $reg->user->avatarAsset?->getPublicUrl(),
                ],
                'scoring_session' => $session,
            ];
        }

        return ApiResponse::success($response);
    }

    /** Save scores for an entire target butt for a specific end (organizer or authorized scorer). */
    public function saveEndScores(
        Request $request,
        Event $event,
        EventDivision $division,
        int $target_butt,
        int $end_number
    ): JsonResponse {
        if (!$this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to input scores.', 403);
        }

        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'scores.*.arrows' => ['required', 'array', 'size:6'],
            'scores.*.arrows.*.score_value' => ['required', 'integer', 'between:0,10'],
            'scores.*.arrows.*.is_x' => ['required', 'boolean'],
            'scores.*.arrows.*.is_miss' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($division, $end_number, $request): void {
            foreach ($request->input('scores') as $entry) {
                $userId = $entry['user_id'];
                $arrowsData = $entry['arrows'];

                // Retrieve athlete competitive session
                $session = ScoringSession::query()
                    ->where('user_id', $userId)
                    ->where('event_division_id', $division->id)
                    ->firstOrFail();

                // Find or create end
                $end = ScoringEnd::query()->firstOrCreate([
                    'scoring_session_id' => $session->id,
                    'end_number' => $end_number,
                ]);

                // Delete existing arrows for this end (for edits)
                $end->arrows()->delete();

                // Insert new arrows & calculate end total
                $endTotal = 0;
                foreach ($arrowsData as $idx => $arr) {
                    $scoreValue = $arr['score_value'];
                    $endTotal += $scoreValue;

                    ScoringArrow::query()->create([
                        'id' => (string) Str::ulid(),
                        'scoring_end_id' => $end->id,
                        'arrow_index' => $idx,
                        'score_value' => $scoreValue,
                        'is_x' => $arr['is_x'],
                        'is_miss' => $arr['is_miss'],
                    ]);
                }

                $end->update(['end_total' => $endTotal]);

                // Recalculate competitive session aggregates
                $allArrows = ScoringArrow::query()
                    ->whereIn('scoring_end_id', $session->ends()->pluck('id'))
                    ->get();

                $totalScore = $allArrows->sum('score_value');
                $xCount = $allArrows->where('is_x', true)->count();
                $tenCount = $allArrows->where('score_value', 10)->count(); // includes X
                $missCount = $allArrows->where('is_miss', true)->count();
                $arrowsShot = $allArrows->count();
                $avgPerArrow = $arrowsShot > 0 ? $totalScore / $arrowsShot : 0;

                $session->total_score = $totalScore;
                $session->max_possible_score = $session->num_ends * $session->arrows_per_end * 10;
                $session->arrows_shot = $arrowsShot;
                $session->avg_per_arrow = $avgPerArrow;
                $session->x_count = $xCount;
                $session->ten_count = $tenCount;
                $session->miss_count = $missCount;
                $session->save();
            }
        });

        return ApiResponse::success(null, "Skor rambahan {$end_number} berhasil disimpan.");
    }

    /** Display live leaderboard for an event division. */
    public function getLeaderboard(Event $event, EventDivision $division): JsonResponse
    {
        // Get competitive sessions for this division
        $sessions = ScoringSession::query()
            ->where('event_division_id', $division->id)
            ->with(['user'])
            ->get();

        $leaderboard = $sessions->map(function ($session) use ($division) {
            $reg = EventRegistration::query()
                ->where('event_division_id', $division->id)
                ->where('user_id', $session->user_id)
                ->first();

            return [
                'session_id' => $session->id,
                'user_id' => $session->user_id,
                'user_name' => $session->user?->name,
                'bib_number' => $reg?->bib_number,
                'target_butt' => $reg?->target_butt,
                'target_letter' => $reg?->target_letter,
                'total_score' => $session->total_score,
                'x_count' => $session->x_count,
                'ten_count' => $session->ten_count,
                'miss_count' => $session->miss_count,
                'arrows_shot' => $session->arrows_shot,
                'avg_per_arrow' => $session->avg_per_arrow,
            ];
        })
        ->sortBy([
            ['total_score', 'desc'],
            ['x_count', 'desc'],
            ['ten_count', 'desc'],
            ['miss_count', 'asc'],
        ])
        ->values()
        ->all();

        return ApiResponse::success($leaderboard);
    }
}
