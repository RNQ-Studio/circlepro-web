<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $categories = QueryBuilder::for(Category::class)
            ->allowedFilters(
                'name',
                'slug',
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'slug', 'is_active', 'created_at', 'updated_at')
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(
            data: CategoryResource::collection($categories->getCollection())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                ],
            ],
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        return ApiResponse::success(new CategoryResource($category), 'Category created', 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return ApiResponse::success(new CategoryResource($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return ApiResponse::success(new CategoryResource($category->refresh()), 'Category updated');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return ApiResponse::success(null, 'Category deleted');
    }
}
