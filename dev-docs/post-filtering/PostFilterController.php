<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * PostFilterController
 *
 * One controller exposing all four filtering strategies on separate routes.
 * Useful for side-by-side comparison during learning / benchmarking.
 *
 * Routes:
 *   GET /api/v1/posts/eloquent
 *   GET /api/v1/posts/query-builder
 *   GET /api/v1/posts/raw
 *   GET /api/v1/posts/spatie
 *
 * All share the same JSON API query params:
 *   filter[status]=published
 *   filter[category_id]=1
 *   filter[author_id]=2
 *   filter[search]=laravel
 *   filter[published_from]=2024-01-01
 *   filter[published_to]=2024-12-31
 *   filter[tags][]=php&filter[tags][]=api
 *   filter[is_featured]=true
 *   filter[min_views]=100
 *   sort=-published_at,title
 *   page[number]=1
 *   page[size]=15
 *   include=author,category,tags          (eloquent & spatie)
 *   fields[posts]=id,title,slug,status    (spatie & query-builder)
 */
class PostFilterController extends Controller
{
    // ════════════════════════════════════════════════════════════
    // 1. ELOQUENT ORM
    // ════════════════════════════════════════════════════════════

    public function eloquent(Request $request): JsonResponse
    {
        $query = Post::query()->with($this->resolveIncludes($request));

        $this->eloquentFilters($query, $request);
        $this->eloquentSort($query, $request);

        [$size, $page] = $this->pagination($request);
        $paginator = $query->paginate($size, ['*'], 'page[number]', $page);

        return response()->json([
            'data'  => PostResource::collection($paginator->items()),
            'meta'  => $this->meta($paginator),
            'links' => $this->links($paginator),
        ]);
    }

    private function eloquentFilters($query, Request $request): void
    {
        $f = $request->input('filter', []);

        if (!empty($f['status']))       $query->where('status', $f['status']);
        if (!empty($f['statuses']))     $query->whereIn('status', (array) $f['statuses']);
        if (!empty($f['category_id'])) $query->where('category_id', (int) $f['category_id']);
        if (!empty($f['author_id']))   $query->where('user_id', (int) $f['author_id']);

        if (!empty($f['search'])) {
            $t = $f['search'];
            $query->where(fn($q) => $q->where('title', 'LIKE', "%$t%")->orWhere('body', 'LIKE', "%$t%"));
        }

        if (!empty($f['published_from'])) $query->whereDate('published_at', '>=', $f['published_from']);
        if (!empty($f['published_to']))   $query->whereDate('published_at', '<=', $f['published_to']);

        if (!empty($f['tags']) && is_array($f['tags'])) {
            $query->whereHas('tags', fn($q) => $q->whereIn('slug', $f['tags']));
        }

        if (isset($f['is_featured'])) {
            $query->where('is_featured', filter_var($f['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($f['min_views'])) $query->where('views_count', '>=', (int) $f['min_views']);
    }

    private function eloquentSort($query, Request $request): void
    {
        $allowed = ['title', 'published_at', 'created_at', 'views_count', 'likes_count'];

        foreach (explode(',', $request->input('sort', '-published_at')) as $field) {
            $dir    = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) $query->orderBy($column, $dir);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 2. QUERY BUILDER (DB facade)
    // ════════════════════════════════════════════════════════════

    public function queryBuilder(Request $request): JsonResponse
    {
        $columns = $this->sparseFields($request);

        $query = DB::table('posts as p')
            ->select($columns)
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->addSelect(['u.name as author_name', 'c.name as category_name', 'c.slug as category_slug']);

        $this->qbFilters($query, $request);
        $this->qbSort($query, $request);

        [$size, $page] = $this->pagination($request);
        $total    = (clone $query)->count();
        $lastPage = (int) ceil($total / $size);
        $items    = $query->limit($size)->offset(($page - 1) * $size)->get();

        return response()->json([
            'data'  => $items,
            'meta'  => ['current_page' => $page, 'per_page' => $size, 'total' => $total, 'last_page' => $lastPage],
            'links' => $this->manualLinks($request, $page, $lastPage),
        ]);
    }

    private function qbFilters($query, Request $request): void
    {
        $f = $request->input('filter', []);

        if (!empty($f['status']))       $query->where('p.status', $f['status']);
        if (!empty($f['statuses']))     $query->whereIn('p.status', (array) $f['statuses']);
        if (!empty($f['category_id'])) $query->where('p.category_id', (int) $f['category_id']);
        if (!empty($f['author_id']))   $query->where('p.user_id', (int) $f['author_id']);

        if (!empty($f['search'])) {
            $t = '%' . $f['search'] . '%';
            $query->where(fn($q) => $q->where('p.title', 'LIKE', $t)->orWhere('p.body', 'LIKE', $t));
        }

        if (!empty($f['published_from'])) $query->whereDate('p.published_at', '>=', $f['published_from']);
        if (!empty($f['published_to']))   $query->whereDate('p.published_at', '<=', $f['published_to']);

        if (!empty($f['tags']) && is_array($f['tags'])) {
            $slugs = $f['tags'];
            $query->whereExists(function ($sub) use ($slugs) {
                $sub->select(DB::raw(1))
                    ->from('post_tag as pt')
                    ->join('tags as t', 't.id', '=', 'pt.tag_id')
                    ->whereColumn('pt.post_id', 'p.id')
                    ->whereIn('t.slug', $slugs);
            });
        }

        if (isset($f['is_featured'])) {
            $query->where('p.is_featured', filter_var($f['is_featured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        if (!empty($f['min_views'])) $query->where('p.views_count', '>=', (int) $f['min_views']);
    }

    private function qbSort($query, Request $request): void
    {
        $allowed = ['title', 'published_at', 'created_at', 'views_count', 'likes_count'];

        foreach (explode(',', $request->input('sort', '-published_at')) as $field) {
            $dir    = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) $query->orderBy("p.$column", $dir);
        }
    }

    // ════════════════════════════════════════════════════════════
    // 3. RAW DB STATEMENT
    // ════════════════════════════════════════════════════════════

    public function raw(Request $request): JsonResponse
    {
        $f = $request->input('filter', []);

        [$clauses, $bindings] = $this->rawWhere($f);
        $where   = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        $orderBy = $this->rawOrderBy($request);

        [$size, $page] = $this->pagination($request);
        $offset = ($page - 1) * $size;

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS total
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             $where",
            $bindings
        )?->total;

        $items = DB::select(
            "SELECT p.id, p.title, p.slug, p.status, p.is_featured,
                    p.views_count, p.likes_count, p.published_at, p.created_at,
                    u.id AS author_id, u.name AS author_name,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN categories c ON c.id = p.category_id
             $where
             ORDER BY $orderBy
             LIMIT ? OFFSET ?",
            array_merge($bindings, [$size, $offset])
        );

        // Batch-load tags (prevents N+1)
        $postIds = array_column($items, 'id');
        $tags    = $this->rawFetchTags($postIds);
        foreach ($items as $item) {
            $item->tags = $tags[$item->id] ?? [];
        }

        $lastPage = (int) ceil($total / $size);

        return response()->json([
            'data'  => $items,
            'meta'  => ['current_page' => $page, 'per_page' => $size, 'total' => $total, 'last_page' => $lastPage],
            'links' => $this->manualLinks($request, $page, $lastPage),
        ]);
    }

    private function rawWhere(array $f): array
    {
        $clauses = [];
        $b       = [];

        if (!empty($f['status'])) {
            $clauses[] = 'p.status = ?';
            $b[] = $f['status'];
        }
        if (!empty($f['category_id'])) {
            $clauses[] = 'p.category_id = ?';
            $b[] = (int) $f['category_id'];
        }
        if (!empty($f['author_id'])) {
            $clauses[] = 'p.user_id = ?';
            $b[] = (int) $f['author_id'];
        }

        if (!empty($f['statuses']) && is_array($f['statuses'])) {
            $ph = implode(',', array_fill(0, count($f['statuses']), '?'));
            $clauses[] = "p.status IN ($ph)";
            array_push($b, ...$f['statuses']);
        }

        if (!empty($f['search'])) {
            $t = '%' . $f['search'] . '%';
            $clauses[] = '(p.title LIKE ? OR p.body LIKE ?)';
            $b[] = $t;
            $b[] = $t;
        }

        if (!empty($f['published_from'])) {
            $clauses[] = 'DATE(p.published_at) >= ?';
            $b[] = $f['published_from'];
        }
        if (!empty($f['published_to'])) {
            $clauses[] = 'DATE(p.published_at) <= ?';
            $b[] = $f['published_to'];
        }
        if (!empty($f['min_views'])) {
            $clauses[] = 'p.views_count >= ?';
            $b[] = (int) $f['min_views'];
        }

        if (isset($f['is_featured'])) {
            $clauses[] = 'p.is_featured = ?';
            $b[] = filter_var($f['is_featured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (!empty($f['tags']) && is_array($f['tags'])) {
            $ph = implode(',', array_fill(0, count($f['tags']), '?'));
            $clauses[] = "EXISTS (SELECT 1 FROM post_tag pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id = p.id AND t.slug IN ($ph))";
            array_push($b, ...$f['tags']);
        }

        return [$clauses, $b];
    }

    private function rawOrderBy(Request $request): string
    {
        $allowed = ['title', 'published_at', 'created_at', 'views_count', 'likes_count'];
        $parts   = [];

        foreach (explode(',', $request->input('sort', '-published_at')) as $field) {
            $dir    = str_starts_with($field, '-') ? 'DESC' : 'ASC';
            $column = ltrim($field, '-');
            if (in_array($column, $allowed, true)) $parts[] = "p.$column $dir";
        }

        return $parts ? implode(', ', $parts) : 'p.published_at DESC';
    }

    private function rawFetchTags(array $ids): array
    {
        if (empty($ids)) return [];

        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select(
            "SELECT pt.post_id, t.id, t.name, t.slug FROM post_tag pt JOIN tags t ON t.id = pt.tag_id WHERE pt.post_id IN ($ph) ORDER BY t.name",
            $ids
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row->post_id][] = ['id' => $row->id, 'name' => $row->name, 'slug' => $row->slug];
        }
        return $map;
    }

    // ════════════════════════════════════════════════════════════
    // 4. SPATIE QUERY BUILDER
    // ════════════════════════════════════════════════════════════

    public function spatie(Request $request)
    {
        $posts = QueryBuilder::for(Post::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('author_id', 'user_id'),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::partial('title'),
                AllowedFilter::callback(
                    'search',
                    fn($q, $v) =>
                    $q->where(fn($s) => $s->where('title', 'LIKE', "%$v%")->orWhere('body', 'LIKE', "%$v%"))
                ),
                AllowedFilter::scope('published_from'),
                AllowedFilter::scope('published_to'),
                AllowedFilter::scope('tags'),
                AllowedFilter::scope('search'),
                AllowedFilter::scope('min_views'),
            )
            ->allowedSorts(
                AllowedSort::field('title'),
                AllowedSort::field('created_at'),
                AllowedSort::field('views_count'),
                AllowedSort::field('likes_count'),
                AllowedSort::field('published_at')->defaultDirection(SortDirection::Descending),
                AllowedSort::callback('author_name', function ($q, bool $desc) {
                    $q->join('users', 'users.id', '=', 'posts.user_id')
                        ->orderBy('users.name', $desc ? 'desc' : 'asc')
                        ->select('posts.*');
                }),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('author'),
                AllowedInclude::relationship('category'),
                AllowedInclude::relationship('tags'),
                AllowedInclude::count('tagsCount'),
            )
            ->allowedFields(
                'id',
                'title',
                'slug',
                'status',
                'is_featured',
                'views_count',
                'likes_count',
                'published_at',
                'created_at',
            )
            ->jsonPaginate();

        return PostResource::collection($posts);
    }

    // ════════════════════════════════════════════════════════════
    // Shared helpers
    // ════════════════════════════════════════════════════════════

    private function pagination(Request $request): array
    {
        return [
            min((int) $request->input('page.size', 15), 100),
            max((int) $request->input('page.number', 1), 1),
        ];
    }

    private function resolveIncludes(Request $request): array
    {
        return array_intersect(
            explode(',', $request->input('include', '')),
            ['author', 'category', 'tags']
        );
    }

    private function sparseFields(Request $request): array
    {
        $allowed   = ['id', 'title', 'slug', 'status', 'is_featured', 'views_count', 'likes_count', 'published_at', 'created_at'];
        $requested = $request->input('fields.posts')
            ? array_map('trim', explode(',', $request->input('fields.posts')))
            : $allowed;

        return array_map(fn($f) => "p.$f", array_intersect($requested, $allowed) ?: $allowed);
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];
    }

    private function links($paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last'  => $paginator->url($paginator->lastPage()),
            'prev'  => $paginator->previousPageUrl(),
            'next'  => $paginator->nextPageUrl(),
        ];
    }

    private function manualLinks(Request $request, int $page, int $last): array
    {
        $base = fn($p) => $request->fullUrlWithQuery(['page' => ['number' => $p]]);
        return [
            'first' => $base(1),
            'last'  => $base($last),
            'prev'  => $page > 1    ? $base($page - 1) : null,
            'next'  => $page < $last ? $base($page + 1) : null,
        ];
    }
}
