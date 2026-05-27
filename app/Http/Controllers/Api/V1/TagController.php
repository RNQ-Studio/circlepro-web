<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTagRequest;
use App\Http\Requests\Api\V1\UpdateTagRequest;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $tags = QueryBuilder::for(Tag::class)
            ->allowedFilters('name', 'slug')
            ->allowedSorts('name', 'slug', 'created_at')
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(TagResource::collection($tags));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::query()->create($request->validated());

        return ApiResponse::success(new TagResource($tag), 'Tag created', 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return ApiResponse::success(new TagResource($tag));
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $tag->update($request->validated());

        return ApiResponse::success(new TagResource($tag->refresh()), 'Tag updated');
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return ApiResponse::success(null, 'Tag deleted');
    }
}
