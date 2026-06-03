<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\ScoringSession;
use App\Support\ApiResponse;
use App\Support\Enums\PostVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Community feed posts (Module 5, task 2.11). Feed = public posts + posts from
 * the user's clubs + their own, newest first.
 */
class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $myClubIds = $request->user()->organizationMemberships()->pluck('organization_id');

        $base = Post::query();

        if ($request->query('feed') === 'following') {
            $followingIds = $request->user()->followings()->pluck('users.id')->push($userId);
            $base->whereIn('author_id', $followingIds);
        }

        $base->where(function (Builder $q) use ($userId, $myClubIds): void {
                $q->where('visibility', PostVisibility::Public->value)
                    ->orWhere('author_id', $userId)
                    ->orWhere(function (Builder $club) use ($myClubIds): void {
                        $club->where('visibility', PostVisibility::Club->value)
                            ->whereIn('organization_id', $myClubIds);
                    });
            })
            ->with('author.profile')
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $userId)]);

        $posts = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('author_id'),
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100))
            ->appends($request->query());

        return ApiResponse::success(PostResource::collection($posts));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A shared scoring session must belong to the author (task 2.13a).
        if (($data['shared_type'] ?? null) === 'scoring_session') {
            $owns = ScoringSession::query()
                ->whereKey($data['shared_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            abort_unless($owns, 403, 'You can only share your own scoring session.');
        }

        $post = Post::query()->create([
            'author_id' => $request->user()->id,
            'organization_id' => $data['organization_id'] ?? null,
            'body' => $data['body'] ?? null,
            'visibility' => $data['visibility'] ?? PostVisibility::Public->value,
            'shared_type' => $data['shared_type'] ?? null,
            'shared_id' => $data['shared_id'] ?? null,
        ]);

        return ApiResponse::success(
            new PostResource($post->load('author.profile')),
            'Post created',
            201,
        );
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $post->load('author.profile')
            ->loadExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $request->user()->id)]);

        return ApiResponse::success(new PostResource($post));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->author_id === $request->user()->id, 403, 'Not your post.');

        $post->delete();

        return ApiResponse::success(null, 'Post deleted');
    }

    public function like(Request $request, Post $post): JsonResponse
    {
        $like = PostLike::query()->firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $post->increment('like_count');
        }

        return ApiResponse::success(['liked' => true, 'like_count' => $post->refresh()->like_count]);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $deleted = PostLike::query()
            ->where('post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($deleted > 0) {
            $post->decrement('like_count');
        }

        return ApiResponse::success(['liked' => false, 'like_count' => $post->refresh()->like_count]);
    }
}
