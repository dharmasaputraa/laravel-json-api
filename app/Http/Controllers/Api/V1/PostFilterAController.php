<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Post Filtering — Approach A (JsonApiResource)
 *
 * Same 4 filtering strategies as PostFilterController, but every response
 * goes through PostResource (JsonApiResource) — the proper Approach A way.
 */
class PostFilterAController extends BaseApiController
{
    /**
     * Strategy 1: Pure Eloquent (Approach A).
     *
     * Builds the query with Eloquent, returns via PostResource.
     *
     * @unauthenticated
     */
    #[QueryParameter('filter.status', description: 'Filter by status (draft,published,archived)', type: 'string', example: 'published')]
    #[QueryParameter('filter.is_featured', description: 'Filter featured posts', type: 'boolean', example: true)]
    #[QueryParameter('filter.search', description: 'Search title and body', type: 'string', example: 'laravel')]
    #[QueryParameter('filter.tags[]', description: 'Filter by tag slugs', type: 'string', example: 'eloquent')]
    #[QueryParameter('filter.published_from', description: 'Published after date', type: 'string', example: '2024-01-01')]
    #[QueryParameter('filter.published_to', description: 'Published before date', type: 'string', example: '2024-12-31')]
    #[QueryParameter('sort', description: 'Sort. Prefix - for desc. Allowed: published_at,title,views_count,created_at', type: 'string', example: '-published_at')]
    #[QueryParameter('page.size', description: 'Items per page', type: 'int', example: 15)]
    public function eloquent(Request $request)
    {
        $query = Post::with(['author', 'category', 'tags']);

        // Filter by status
        if ($status = $request->query('filter.status')) {
            $query->where('status', $status);
        }

        // Filter by is_featured
        if ($request->has('filter.is_featured')) {
            $query->where('is_featured', filter_var($request->query('filter.is_featured'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by search (title + body)
        if ($search = $request->query('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('body', 'LIKE', "%{$search}%");
            });
        }

        // Filter by tags
        if ($tags = $request->query('filter.tags')) {
            $tags = is_array($tags) ? $tags : [$tags];
            $query->whereHas('tags', fn($q) => $q->whereIn('slug', $tags));
        }

        // Filter by date range
        if ($from = $request->query('filter.published_from')) {
            $query->where('published_at', '>=', $from);
        }
        if ($to = $request->query('filter.published_to')) {
            $query->where('published_at', '<=', $to);
        }

        // Sort
        $sort = $request->query('sort', '-published_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $allowedSorts = ['published_at', 'title', 'views_count', 'created_at'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        }

        $perPage = (int) ($request->input('page.size', 15));

        // ✅ Approach A: Let JsonApiResource handle the envelope
        return PostResource::collection($query->paginate($perPage));
    }

    /**
     * Strategy 2: Query Builder (Approach A).
     *
     * Uses DB::table for the query, then hydrates Eloquent models
     * so PostResource can format the response properly.
     *
     * @unauthenticated
     */
    #[QueryParameter('filter.status', description: 'Filter by status', type: 'string', example: 'published')]
    #[QueryParameter('filter.search', description: 'Search title and body', type: 'string', example: 'laravel')]
    #[QueryParameter('filter.tags[]', description: 'Filter by tag slugs', type: 'string', example: 'eloquent')]
    #[QueryParameter('filter.published_from', description: 'Published after date', type: 'string', example: '2024-01-01')]
    #[QueryParameter('filter.published_to', description: 'Published before date', type: 'string', example: '2024-12-31')]
    #[QueryParameter('sort', description: 'Sort field', type: 'string', example: '-published_at')]
    #[QueryParameter('page.size', description: 'Items per page', type: 'int', example: 15)]
    public function queryBuilder(Request $request)
    {
        $query = DB::table('posts')
            ->select('posts.*')
            ->leftJoin('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('categories', 'posts.category_id', '=', 'categories.id');

        // Filter by status
        if ($status = $request->query('filter.status')) {
            $query->where('posts.status', $status);
        }

        // Filter by search
        if ($search = $request->query('filter.search')) {
            $query->where(function ($q) use ($search) {
                $q->where('posts.title', 'LIKE', "%{$search}%")
                    ->orWhere('posts.body', 'LIKE', "%{$search}%");
            });
        }

        // Filter by tags (via pivot)
        if ($tags = $request->query('filter.tags')) {
            $tags = is_array($tags) ? $tags : [$tags];
            $query->join('post_tag', 'posts.id', '=', 'post_tag.post_id')
                ->join('tags', 'post_tag.tag_id', '=', 'tags.id')
                ->whereIn('tags.slug', $tags);
        }

        // Filter by date range
        if ($from = $request->query('filter.published_from')) {
            $query->where('posts.published_at', '>=', $from);
        }
        if ($to = $request->query('filter.published_to')) {
            $query->where('posts.published_at', '<=', $to);
        }

        // Sort
        $sort = $request->query('sort', '-published_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy('posts.' . $field, $direction);

        $perPage = (int) ($request->input('page.size', 15));
        $paginated = $query->paginate($perPage);

        // ✅ Approach A: Hydrate Eloquent models from raw results, then use PostResource
        $postIds = collect($paginated->items())->pluck('id')->all();
        $posts = Post::with(['author', 'category', 'tags'])
            ->whereIn('id', $postIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $postIds) . ')')
            ->get();

        // Manually paginate the collection to preserve meta
        $postsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $posts,
            $paginated->total(),
            $paginated->perPage(),
            $paginated->currentPage(),
            ['path' => $paginated->path()]
        );

        return PostResource::collection($postsPaginated);
    }

    /**
     * Strategy 3: Raw SQL (Approach A).
     *
     * Uses raw SQL for the query, then hydrates Eloquent models
     * so PostResource can format the response properly.
     *
     * @unauthenticated
     */
    #[QueryParameter('filter.status', description: 'Filter by status', type: 'string', example: 'published')]
    #[QueryParameter('filter.published_from', description: 'Published after date', type: 'string', example: '2024-01-01')]
    #[QueryParameter('filter.published_to', description: 'Published before date', type: 'string', example: '2024-12-31')]
    #[QueryParameter('sort', description: 'Sort field', type: 'string', example: '-views_count')]
    #[QueryParameter('page.number', description: 'Page number', type: 'int', example: 1)]
    #[QueryParameter('page.size', description: 'Items per page', type: 'int', example: 15)]
    public function raw(Request $request)
    {
        $bindings = [];
        $wheres = [];

        // Filter by status
        if ($status = $request->query('filter.status')) {
            $wheres[] = 'p.status = ?';
            $bindings[] = $status;
        }

        // Filter by date range
        if ($from = $request->query('filter.published_from')) {
            $wheres[] = 'p.published_at >= ?';
            $bindings[] = $from;
        }
        if ($to = $request->query('filter.published_to')) {
            $wheres[] = 'p.published_at <= ?';
            $bindings[] = $to;
        }

        $whereClause = count($wheres) > 0 ? 'WHERE ' . implode(' AND ', $wheres) : '';

        // Sort
        $sort = $request->query('sort', '-published_at');
        $direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $field = ltrim($sort, '-');
        $allowedSorts = ['published_at', 'title', 'views_count', 'created_at'];
        if (!in_array($field, $allowedSorts)) {
            $field = 'published_at';
        }

        $page = (int) ($request->input('page.number', 1));
        $size = (int) ($request->input('page.size', 15));
        $offset = ($page - 1) * $size;

        // Count total
        $countBindings = $bindings;
        $countSql = "SELECT COUNT(*) as total FROM posts p " . $whereClause;
        $total = DB::selectOne($countSql, $countBindings)->total ?? 0;

        // Fetch IDs via raw SQL
        $sql = "SELECT p.id FROM posts p "
            . ($whereClause ? $whereClause . " " : '')
            . "ORDER BY p.{$field} {$direction} "
            . "LIMIT {$size} OFFSET {$offset}";

        $rows = DB::select($sql, $bindings);
        $postIds = collect($rows)->pluck('id')->all();

        // ✅ Approach A: Hydrate Eloquent models preserving raw SQL order, then use PostResource
        $posts = Post::with(['author', 'category', 'tags'])
            ->whereIn('id', $postIds)
            ->orderByRaw('FIELD(id, ' . implode(',', $postIds) . ')')
            ->get();

        $postsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $posts,
            $total,
            $size,
            $page,
            ['path' => request()->url()]
        );

        return PostResource::collection($postsPaginated);
    }

    /**
     * Strategy 4: Spatie Query Builder (Approach A).
     *
     * Uses spatie/laravel-query-builder for declarative filtering,
     * returns via PostResource directly.
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
    public function spatie(Request $request)
    {
        $posts = QueryBuilder::for(Post::class)
            ->allowedFilters(
                'status',
                'is_featured',
                AllowedFilter::partial('search', 'title'),
                AllowedFilter::scope('published_from'),
                AllowedFilter::scope('published_to'),
                AllowedFilter::exact('tags', 'tags.slug'),
            )
            ->allowedIncludes('author', 'category', 'tags')
            ->allowedSorts('published_at', 'title', 'views_count', 'created_at')
            ->defaultSort('-published_at')
            ->paginate((int) ($request->input('page.size', 15)));

        // ✅ Approach A: Let JsonApiResource handle everything
        return PostResource::collection($posts);
    }
}
