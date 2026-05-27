<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreArticleRequest;
use App\Http\Requests\Api\V1\UpdateArticleRequest;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Article::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $articles = QueryBuilder::for(Article::query()->with(['category', 'author', 'tags']))
            ->allowedFilters(
                'title',
                'slug',
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('author_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('title', 'status', 'published_at', 'created_at', 'updated_at')
            ->defaultSort('-published_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ArticleResource::collection($articles));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tags = $validated['tags'] ?? [];
        unset($validated['tags']);

        $article = Article::query()->create($validated);

        if (! empty($tags)) {
            $article->tags()->sync($tags);
        }

        return ApiResponse::success(
            new ArticleResource($article->load(['category', 'author', 'tags'])),
            'Article created',
            201
        );
    }

    public function show(Article $article): JsonResponse
    {
        $this->authorize('view', $article);

        return ApiResponse::success(new ArticleResource($article->load(['category', 'author', 'tags'])));
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $validated = $request->validated();
        $tags = $validated['tags'] ?? null;
        unset($validated['tags']);

        $article->update($validated);

        if ($tags !== null) {
            $article->tags()->sync($tags);
        }

        return ApiResponse::success(
            new ArticleResource($article->refresh()->load(['category', 'author', 'tags'])),
            'Article updated'
        );
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $article->delete();

        return ApiResponse::success(null, 'Article deleted');
    }
}
