<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Tag\StoreTagRequest;
use App\Http\Requests\Api\V1\Tag\UpdateTagRequest;
use App\Http\Resources\V1\TagResource;
use App\Models\Tag;
use App\Services\Tag\TagService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Tags
 */
class TagController extends BaseApiController
{
    public function __construct(
        protected TagService $tagService
    ) {}

    /**
     * List all tags.
     *
     * Returns a paginated list of tags with support for filtering, sorting, and including relations.
     *
     * @unauthenticated
     */
    #[QueryParameter('page.size', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('filter[name]', description: 'Filter by name (partial match)', type: 'string', example: 'laravel')]
    #[QueryParameter('filter[slug]', description: 'Filter by slug (partial match)', type: 'string', example: 'eloquent')]
    #[QueryParameter('include', description: 'Comma-separated relations (nested supported). Allowed: posts, posts.category, posts.author, posts.tags', type: 'string', example: 'posts.category')]
    #[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: name,created_at,updated_at', type: 'string', example: '-created_at')]
    #[QueryParameter('fields[tags]', description: 'Sparse fieldset. Allowed: name,slug,description,created_at,updated_at', type: 'string', example: 'name,slug')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset for posts relation. Allowed: id,title,slug', type: 'string', example: 'title,slug')]
    public function index(Request $request)
    {
        $tags = $this->tagService->querySpatieBuilder($request);
        return TagResource::collection($tags);
    }

    /**
     * Create a new tag.
     *
     * @unauthenticated
     */
    public function store(StoreTagRequest $request)
    {
        $tag = $this->tagService->create($request->validated());
        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    /**
     * Show a single tag.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Tag not found.
     *
     * @unauthenticated
     */
    #[PathParameter('tag', description: 'Tag UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: posts', type: 'string', example: 'posts')]
    public function show(Request $request, string $tag)
    {
        $tag = $this->tagService->find($tag, $request);
        return (new TagResource($tag))->response();
    }

    /**
     * Update a tag.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Tag not found.
     *
     * @unauthenticated
     */
    #[PathParameter('tag', description: 'Tag UUID', type: 'string', format: 'uuid')]
    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag = $this->tagService->update($tag, $request->validated());
        return (new TagResource($tag->fresh()))->response();
    }

    /**
     * Delete a tag.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Tag not found.
     *
     * @unauthenticated
     */
    #[PathParameter('tag', description: 'Tag UUID', type: 'string', format: 'uuid')]
    public function destroy(Tag $tag): JsonResponse
    {
        $this->tagService->delete($tag);
        return $this->jsonApiMeta('Tag deleted successfully.');
    }
}