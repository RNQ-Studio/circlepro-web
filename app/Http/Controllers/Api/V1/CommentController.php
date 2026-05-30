<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comments on feed posts (Module 5, task 2.11).
 */
class CommentController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        $comments = $post->comments()
            ->with('author.profile')
            ->orderBy('created_at')
            ->paginate(min(max((int) $request->integer('per_page', 30), 1), 100))
            ->appends($request->query());

        return ApiResponse::success(CommentResource::collection($comments));
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $post->comments()->create([
            'author_id' => $request->user()->id,
            'parent_id' => $request->validated()['parent_id'] ?? null,
            'body' => $request->validated()['body'],
        ]);

        $post->increment('comment_count');

        return ApiResponse::success(
            new CommentResource($comment->load('author.profile')),
            'Comment posted',
            201,
        );
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->author_id === $request->user()->id, 403, 'Not your comment.');

        $comment->delete();
        $comment->post()->decrement('comment_count');

        return ApiResponse::success(null, 'Comment deleted');
    }
}
