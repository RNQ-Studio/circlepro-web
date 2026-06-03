<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CoachReviewResource;
use App\Models\CoachProfile;
use App\Models\CoachReview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachReviewController extends Controller
{
    public function index(Request $request, CoachProfile $coach): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $reviews = CoachReview::query()
            ->where('coach_profile_id', $coach->id)
            ->with(['user.profile'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success(CoachReviewResource::collection($reviews));
    }

    public function store(Request $request, CoachProfile $coach): JsonResponse
    {
        // Prevent coach from reviewing themselves
        if ($coach->user_id === $request->user()->id) {
            return ApiResponse::error('Anda tidak dapat memberikan ulasan untuk diri sendiri.', 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = CoachReview::query()->updateOrCreate(
            [
                'coach_profile_id' => $coach->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        return ApiResponse::success(new CoachReviewResource($review->load('user.profile')), 'Ulasan berhasil disimpan.', 201);
    }
}
