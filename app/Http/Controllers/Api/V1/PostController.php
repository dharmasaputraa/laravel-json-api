<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Post\StorePostRequest;
use App\Http\Requests\Api\V1\Post\UpdatePostRequest;
use App\Http\Resources\V1\PostManualResource;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use App\Services\Post\PostService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Posts
 */
class PostController extends BaseApiController
{
    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * List all posts (Approach A — JsonApiResource).
     *
     * Returns a paginated list of posts using Laravel's JsonApiResource.
     *
     * @unauthenticated
     */
    #[QueryParameter('page.size', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('filter[title]', description: 'Filter by title (partial match)', type: 'string', example: 'laravel')]
    #[QueryParameter('filter[status]', description: 'Filter by status (draft,published,archived)', type: 'string', example: 'published')]
    #[QueryParameter('filter[is_featured]', description: 'Filter featured posts', type: 'boolean', example: true)]
    #[QueryParameter('filter[search]', description: 'Partial search on title', type: 'string', example: 'eloquent')]
    #[QueryParameter('filter[published_from]', description: 'Published after date (scope)', type: 'string', example: '2024-01-01')]
    #[QueryParameter('filter[published_to]', description: 'Published before date (scope)', type: 'string', example: '2024-12-31')]
    #[QueryParameter('filter[tags]', description: 'Filter by tag slug (exact)', type: 'string', example: 'eloquent')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,category,tags')]
    #[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: title,created_at,updated_at,published_at,views_count', type: 'string', example: '-published_at')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset. Allowed: title,slug,body,status,is_featured,views_count,published_at,created_at,updated_at', type: 'string', example: 'title,slug')]
    #[QueryParameter('fields[users]', description: 'Sparse fieldset for author. Allowed: id,name,email', type: 'string', example: 'name')]
    #[QueryParameter('fields[categories]', description: 'Sparse fieldset for category. Allowed: id,name,slug', type: 'string', example: 'name,slug')]
    #[QueryParameter('fields[tags]', description: 'Sparse fieldset for tags. Allowed: id,name,slug', type: 'string', example: 'name')]
    public function index(Request $request)
    {
        $posts = $this->postService->querySpatieBuilder($request);
        return PostResource::collection($posts);
    }

    /**
     * Create a new post.
     *
     * @unauthenticated
     */
    public function store(StorePostRequest $request)
    {
        $post = $this->postService->create($request->validated());
        return (new PostResource($post))->response()->setStatusCode(201);
    }

    /**
     * Show a single post (Approach A — JsonApiResource).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,tags')]
    public function show(Request $request, string $post)
    {
        $post = $this->postService->find($post, $request);
        return (new PostResource($post))->response();
    }

    /**
     * Update a post.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    public function update(UpdatePostRequest $request, Post $post)
    {
        $post = $this->postService->update($post, $request->validated());
        return (new PostResource($post->fresh(['author', 'category', 'tags'])))->response();
    }

    /**
     * Delete a post.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    public function destroy(Post $post): JsonResponse
    {
        $this->postService->delete($post);
        return $this->jsonApiMeta('Post deleted successfully.');
    }

    // ───────────────────────────────────────────────────────────
    // Approach B: Manual JSON:API envelope (PostManualResource)
    // ───────────────────────────────────────────────────────────

    /**
     * List all posts (Approach B — Manual JSON:API).
     *
     * Same data as index(), but uses PostManualResource which builds
     * the JSON:API envelope manually without JsonApiResource base class.
     *
     * @unauthenticated
     */
    #[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,tags')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset. Allowed: title,slug,body,status,is_featured,views_count,published_at,created_at,updated_at', type: 'string', example: 'title,slug')]
    public function indexManual(Request $request)
    {
        $posts = $this->postService->query($request);
        return PostManualResource::collection($posts);
    }

    /**
     * Show a single post (Approach B — Manual JSON:API).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,tags')]
    public function showManual(Request $request, string $post)
    {
        $post = $this->postService->find($post, $request);
        return new PostManualResource($post);
    }

    // ───────────────────────────────────────────────────────────
    // Approach C (ANTI-PATTERN): Inline response in controller
    //
    // This is intentionally WRONG. It shows what NOT to do:
    // building the JSON:API response array directly in the
    // controller without any Resource class.
    //
    // Problems with this approach:
    //  • Violates Single Responsibility Principle
    //  • No reusability — can't reuse transformation elsewhere
    //  • Hard to test — response logic buried in controller
    //  • Hard to maintain — spec changes require editing controllers
    //  • No type safety or IDE autocompletion for response shape
    // ───────────────────────────────────────────────────────────

    /**
     * List posts (Approach C — Inline, NO Resource class).
     *
     * Anti-pattern: response structure built directly in the controller.
     * Compare with index() (Approach A) and indexManual() (Approach B)
     * to see why Resource classes are important.
     *
     * @unauthenticated
     */
    #[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,tags')]
    public function indexInline(Request $request): JsonResponse
    {
        $posts = $this->postService->query($request);

        // BAD: Manually building every item's JSON:API shape in the controller
        $data = $posts->map(function ($post) {
            $item = [
                'type' => 'posts',
                'id' => (string) $post->id,
                'attributes' => [
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'body' => $post->body,
                    'status' => $post->status?->value,
                    'is_featured' => $post->is_featured,
                    'views_count' => $post->views_count,
                    'published_at' => $post->published_at,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ],
                'relationships' => [],
                'links' => [
                    'self' => route('api.v1.posts-inline.show', ['post' => $post->id]),
                ],
            ];

            // BAD: relationship logic scattered in controller
            if ($post->relationLoaded('author') && $post->author) {
                $item['relationships']['author'] = [
                    'data' => ['type' => 'users', 'id' => (string) $post->author->id],
                ];
            }
            if ($post->relationLoaded('category') && $post->category) {
                $item['relationships']['category'] = [
                    'data' => ['type' => 'categories', 'id' => (string) $post->category->id],
                ];
            }
            if ($post->relationLoaded('tags')) {
                $item['relationships']['tags'] = [
                    'data' => $post->tags->map(fn($tag) => ['type' => 'tags', 'id' => (string) $tag->id])->all(),
                ];
            }

            return $item;
        })->all();

        // BAD: pagination meta built by hand
        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => $data,
            'meta' => [
                'current_page' => $posts->currentPage(),
                'from' => $posts->firstItem(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'to' => $posts->lastItem(),
                'total' => $posts->total(),
            ],
            'links' => [
                'first' => $posts->url(1),
                'last' => $posts->url($posts->lastPage()),
                'prev' => $posts->previousPageUrl(),
                'next' => $posts->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show a single post (Approach C — Inline, NO Resource class).
     *
     * Anti-pattern: entire JSON:API response hand-coded in the controller.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: author,category,tags', type: 'string', example: 'author,tags')]
    public function showInline(Request $request, string $post): JsonResponse
    {
        $post = $this->postService->find($post, $request);

        // BAD: All response formatting crammed into the controller
        $data = [
            'type' => 'posts',
            'id' => (string) $post->id,
            'attributes' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'body' => $post->body,
                'status' => $post->status?->value,
                'is_featured' => $post->is_featured,
                'views_count' => $post->views_count,
                'published_at' => $post->published_at,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
            'relationships' => [],
            'links' => [
                'self' => route('api.v1.posts-inline.show', ['post' => $post->id]),
            ],
        ];

        // BAD: if you add a new relationship, you must edit this controller
        if ($post->relationLoaded('author') && $post->author) {
            $data['relationships']['author'] = [
                'data' => ['type' => 'users', 'id' => (string) $post->author->id],
            ];
        }
        if ($post->relationLoaded('category') && $post->category) {
            $data['relationships']['category'] = [
                'data' => ['type' => 'categories', 'id' => (string) $post->category->id],
            ];
        }
        if ($post->relationLoaded('tags')) {
            $data['relationships']['tags'] = [
                'data' => $post->tags->map(fn($tag) => ['type' => 'tags', 'id' => (string) $tag->id])->all(),
            ];
        }

        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => $data,
        ]);
    }
}
