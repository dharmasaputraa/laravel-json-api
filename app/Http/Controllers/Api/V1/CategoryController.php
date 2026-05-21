<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Categories
 */
class CategoryController extends BaseApiController
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * List all categories.
     *
     * Returns a paginated list of categories with support for filtering, sorting, and including relations.
     *
     * @unauthenticated
     */
    #[QueryParameter('page.size', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('filter[name]', description: 'Filter by name (partial match)', type: 'string', example: 'tech')]
    #[QueryParameter('filter[slug]', description: 'Filter by slug (partial match)', type: 'string', example: 'laravel')]
    #[QueryParameter('include', description: 'Comma-separated relations (nested supported). Allowed: posts, posts.author, posts.tags', type: 'string', example: 'posts.author')]
    #[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: name,created_at,updated_at', type: 'string', example: '-created_at')]
    #[QueryParameter('fields[categories]', description: 'Sparse fieldset. Allowed: name,slug,description,created_at,updated_at', type: 'string', example: 'name,slug')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset for posts relation. Allowed: id,title,slug', type: 'string', example: 'title,slug')]
    public function index(Request $request)
    {
        $categories = $this->categoryService->querySpatieBuilder($request);
        return CategoryResource::collection($categories);
    }

    /**
     * Create a new category.
     *
     * @unauthenticated
     */
    public function store(StoreCategoryRequest $request)
    {
        $category = $this->categoryService->create($request->validated());
        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * Show a single category.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Category not found.
     *
     * @unauthenticated
     */
    #[PathParameter('category', description: 'Category UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: posts', type: 'string', example: 'posts')]
    public function show(Request $request, string $category)
    {
        $category = $this->categoryService->find($category, $request);
        return (new CategoryResource($category))->response();
    }

    /**
     * Update a category.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Category not found.
     *
     * @unauthenticated
     */
    #[PathParameter('category', description: 'Category UUID', type: 'string', format: 'uuid')]
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category = $this->categoryService->update($category, $request->validated());
        return (new CategoryResource($category->fresh()))->response();
    }

    /**
     * Delete a category.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Category not found.
     *
     * @unauthenticated
     */
    #[PathParameter('category', description: 'Category UUID', type: 'string', format: 'uuid')]
    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);
        return $this->jsonApiMeta('Category deleted successfully.');
    }
}