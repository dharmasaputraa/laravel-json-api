<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Post Filtering (Comparison)
 *
 * Four strategies for filtering posts — same result, different implementation.
 */
class PostFilterController extends BaseApiController
{
    /**
     * Strategy 1: Pure Eloquent with manual scope building.
     *
     * Builds the query manually using Eloquent methods and local scopes.
     *
     * @unauthenticated
     */
    public function eloquent(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('page.size', 15));

        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => PostResource::collection($query->paginate($perPage)),
        ]);
    }

    /**
     * Strategy 2: Query Builder with manual WHERE clauses.
     *
     * Uses Laravel's query builder (DB::table style) for more control.
     *
     * @unauthenticated
     */
    public function queryBuilder(Request $request): JsonResponse
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

        $perPage = (int) ($request->query('page.size', 15));
        $results = $query->paginate($perPage);

        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => $results,
        ]);
    }

    /**
     * Strategy 3: Raw SQL queries.
     *
     * Uses raw SQL for maximum control and performance tuning.
     *
     * @unauthenticated
     */
    public function raw(Request $request): JsonResponse
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

        $page = (int) ($request->query('page.number', 1));
        $size = (int) ($request->query('page.size', 15));
        $offset = ($page - 1) * $size;

        $countSql = "SELECT COUNT(*) as total FROM posts p " . ($whereClause ? "WHERE " . substr($whereClause, 6) : '');
        $total = DB::selectOne(str_replace('p.', '', $countSql), array_slice($bindings, 0))->total ?? 0;

        // Fix: use proper where clause
        $sql = "SELECT p.* FROM posts p "
            . ($whereClause ? $whereClause . " " : '')
            . "ORDER BY p.{$field} {$direction} "
            . "LIMIT {$size} OFFSET {$offset}";

        $posts = DB::select($sql, $bindings);

        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => $posts,
            'meta' => [
                'current_page' => $page,
                'per_page' => $size,
                'total' => $total,
                'last_page' => ceil($total / $size),
            ],
        ]);
    }

    /**
     * Strategy 4: Spatie Query Builder package.
     *
     * Uses spatie/laravel-query-builder for declarative filtering.
     *
     * @unauthenticated
     */
    public function spatie(Request $request): JsonResponse
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
            ->jsonPaginate();

        return response()->json([
            'jsonapi' => ['version' => '1.0'],
            'data' => PostResource::collection($posts),
        ]);
    }
}
