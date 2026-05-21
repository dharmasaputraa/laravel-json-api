<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class BenchmarkReadPosts extends Command
{
    private const STRATEGIES = ['eloquent', 'query_builder', 'raw', 'spatie'];

    protected $signature = 'benchmark:read-posts
        {--strategy= : Strategy to benchmark (eloquent, query_builder, raw, spatie)}
        {--runs=10 : Number of iterations}
        {--items=1000 : Number of items to fetch per run (page size)}
        {--warmup=2 : Warmup runs before measuring}
        {--seed : Auto-run BenchmarkSeeder if DB has < 1000 posts}
        {--with-relations : Include author, category, tags (real-world comparison)}';

    protected $description = 'Benchmark a single read-posts strategy. Run one strategy per process for fair comparison.';

    public function handle(): int
    {
        $strategy = $this->resolveStrategy();
        $runs = (int) $this->option('runs');
        $items = (int) $this->option('items');
        $warmup = (int) $this->option('warmup');
        $withRelations = (bool) $this->option('with-relations');

        $this->printHeader($strategy, $runs, $items, $warmup, $withRelations);

        // Auto-seed check
        $totalPosts = Post::count();
        if ($totalPosts < 1000 && $this->option('seed')) {
            $this->warn("Only {$totalPosts} posts found. Running BenchmarkSeeder...");
            $this->call('db:seed', ['--class' => 'BenchmarkSeeder']);
            $totalPosts = Post::count();
        }

        if ($totalPosts === 0) {
            $this->error('No posts! Run: php artisan migrate:fresh --seed');
            $this->info('  Or for benchmark data: php artisan db:seed --class=BenchmarkSeeder');
            return self::FAILURE;
        }

        $this->info("  Total posts in DB : <fg=cyan>{$totalPosts}</>");
        $this->newLine();

        // ── Connection warmup (separate from strategy warmup) ──
        $this->info('  Warming DB connection...');
        DB::select('SELECT 1');

        // ── Strategy warmup runs ──
        if ($warmup > 0) {
            $this->info("  Warmup runs ({$warmup})...");
            for ($w = 0; $w < $warmup; $w++) {
                $this->executeStrategy($strategy, $items, $withRelations);
            }
            $this->newLine();
        }

        // ── Measured runs ──
        $this->info("  Measuring <fg=yellow>{$runs}</> runs...");
        $times = [];
        $resultCount = 0;

        $progressBar = $this->output->createProgressBar($runs);
        $progressBar->setFormat("    %current%/%max% [%bar%] %elapsed%");

        for ($i = 0; $i < $runs; $i++) {
            gc_collect_cycles();

            $start = hrtime(true);
            $resultCount = $this->executeStrategy($strategy, $items, $withRelations);
            $elapsed = hrtime(true) - $start;

            $times[] = $elapsed;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // ── Results ──
        $this->printResults($strategy, $runs, $resultCount, $times, $withRelations);

        return self::SUCCESS;
    }

    private function resolveStrategy(): string
    {
        $strategy = $this->option('strategy');

        if (!$strategy) {
            $strategy = $this->choice(
                '  Which strategy to benchmark?',
                collect(self::STRATEGIES)->mapWithKeys(fn ($s) => [$s => $s])->toArray(),
                'eloquent'
            );
        }

        if (!in_array($strategy, self::STRATEGIES)) {
            $this->error("Unknown strategy '{$strategy}'. Allowed: " . implode(', ', self::STRATEGIES));
            exit(1);
        }

        return $strategy;
    }

    // ───────────────────────────────────────────────────────
    //  Strategy implementations
    // ───────────────────────────────────────────────────────

    private function executeStrategy(string $strategy, int $pageSize, bool $withRelations): int
    {
        return match ($strategy) {
            'eloquent' => $withRelations
                ? $this->runEloquentWithRelations($pageSize)
                : $this->runEloquent($pageSize),
            'query_builder' => $withRelations
                ? $this->runQueryBuilderWithRelations($pageSize)
                : $this->runQueryBuilder($pageSize),
            'raw' => $withRelations
                ? $this->runRawWithRelations($pageSize)
                : $this->runRaw($pageSize),
            'spatie' => $withRelations
                ? $this->runSpatieWithRelations($pageSize)
                : $this->runSpatie($pageSize),
        };
    }

    // ── Mode 1: Without relations (pure query comparison) ──

    private function runEloquent(int $pageSize): int
    {
        // No eager loading — just the posts table
        $results = Post::where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', 1);

        return $results->count();
    }

    private function runQueryBuilder(int $pageSize): int
    {
        // No joins — just the posts table
        $results = DB::table('posts')
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', 1);

        return $results->count();
    }

    private function runRaw(int $pageSize): int
    {
        $sql = "SELECT * FROM posts "
            . "WHERE status = ? "
            . "ORDER BY published_at DESC "
            . "LIMIT ? OFFSET 0";

        return count(DB::select($sql, ['published', $pageSize]));
    }

    private function runSpatie(int $pageSize): int
    {
        // No includes requested — just the posts query
        $results = QueryBuilder::for(Post::class)
            ->allowedFilters('status')
            ->allowedSorts('published_at')
            ->defaultSort('-published_at')
            ->paginate($pageSize, ['*'], 'page', 1);

        return $results->count();
    }

    // ── Mode 2: With relations (real-world comparison) ──
    // All strategies fetch the SAME data: posts + author + category + tags

    private function runEloquentWithRelations(int $pageSize): int
    {
        // Eloquent eager loading: 1 query posts + 3 queries relations = 4 total
        $results = Post::with(['author', 'category', 'tags'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', 1);

        // Force hydration of relations (ensure they're actually loaded)
        $results->each(fn ($post) => [
            $post->author,
            $post->category,
            $post->tags,
        ]);

        return $results->count();
    }

    private function runQueryBuilderWithRelations(int $pageSize): int
    {
        // QueryBuilder: fetch posts, then manually query each relation
        $posts = DB::table('posts')
            ->where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->paginate($pageSize, ['*'], 'page', 1);

        // Collect IDs for batch relation queries
        $postIds = collect($posts->items())->pluck('id');
        $userIds = collect($posts->items())->pluck('user_id')->unique();
        $categoryIds = collect($posts->items())->pluck('category_id')->unique();

        // Query 2: authors
        DB::table('users')->whereIn('id', $userIds)->get();

        // Query 3: categories
        DB::table('categories')->whereIn('id', $categoryIds)->get();

        // Query 4: tags (via pivot)
        DB::table('tags')
            ->join('post_tag', 'tags.id', '=', 'post_tag.tag_id')
            ->whereIn('post_tag.post_id', $postIds)
            ->get();

        return $posts->count();
    }

    private function runRawWithRelations(int $pageSize): int
    {
        // Raw SQL: 4 separate queries to match Eloquent's eager loading

        // Query 1: posts
        $posts = DB::select(
            "SELECT * FROM posts WHERE status = ? ORDER BY published_at DESC LIMIT ? OFFSET 0",
            ['published', $pageSize]
        );

        $postIds = array_column($posts, 'id');
        $userIds = array_unique(array_column($posts, 'user_id'));
        $categoryIds = array_unique(array_column($posts, 'category_id'));

        if (empty($postIds)) {
            return 0;
        }

        // Query 2: authors
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        DB::select("SELECT * FROM users WHERE id IN ({$placeholders})", array_values($userIds));

        // Query 3: categories
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        DB::select("SELECT * FROM categories WHERE id IN ({$placeholders})", array_values($categoryIds));

        // Query 4: tags via pivot
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        DB::select(
            "SELECT t.*, pt.post_id FROM tags t INNER JOIN post_tag pt ON t.id = pt.tag_id WHERE pt.post_id IN ({$placeholders})",
            array_values($postIds)
        );

        return count($posts);
    }

    private function runSpatieWithRelations(int $pageSize): int
    {
        // Spatie with actual includes requested (simulates ?include=author,category,tags)
        $results = QueryBuilder::for(Post::class)
            ->allowedFilters('status')
            ->allowedIncludes('author', 'category', 'tags')
            ->allowedSorts('published_at')
            ->defaultSort('-published_at')
            ->paginate($pageSize, ['*'], 'page', 1);

        // Force Spatie to actually eager load the relations
        $results->load(['author', 'category', 'tags']);

        return $results->count();
    }

    // ───────────────────────────────────────────────────────
    //  Display helpers
    // ───────────────────────────────────────────────────────

    private function printHeader(string $strategy, int $runs, int $items, int $warmup, bool $withRelations): void
    {
        $modeLabel = $withRelations
            ? '<fg=magenta>WITH relations</> (author + category + tags)'
            : '<fg=blue>WITHOUT relations</> (posts only)';

        $this->newLine();
        $this->info("  ╔══════════════════════════════════════════════════╗");
        $this->info("  ║     Read Posts Benchmark — Single Strategy      ║");
        $this->info("  ╚══════════════════════════════════════════════════╝");
        $this->newLine();
        $this->info("  Strategy      : <fg=green;bold>{$strategy}</>");
        $this->info("  Mode          : {$modeLabel}");
        $this->info("  Runs          : <fg=yellow>{$runs}</>");
        $this->info("  Items/run     : <fg=yellow>{$items}</>");
        $this->info("  Warmup runs   : <fg=yellow>{$warmup}</>");
    }

    private function printResults(string $strategy, int $runs, int $itemsPerRun, array $timesNs, bool $withRelations): void
    {
        $totalMs = $this->nsToMs(array_sum($timesNs));
        $avgMs = $this->nsToMs(array_sum($timesNs) / $runs);
        $minMs = $this->nsToMs(min($timesNs));
        $maxMs = $this->nsToMs(max($timesNs));
        $avgPerItem = $itemsPerRun > 0 ? $avgMs / $itemsPerRun : 0;

        $modeLabel = $withRelations ? 'with relations' : 'without relations';

        $this->info("  ╔═════════════════════════════════════════════════════════╗");
        $this->info("  ║                    RESULTS                             ║");
        $this->info("  ╚═════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Strategy', $strategy],
                ['Mode', $modeLabel],
                ['Runs', (string) $runs],
                ['Items per run', (string) $itemsPerRun],
                ['─── Timing ───', '───'],
                ['Total time', number_format($totalMs, 2) . ' ms'],
                ['Avg per run', number_format($avgMs, 2) . ' ms'],
                ['Min run', number_format($minMs, 2) . ' ms'],
                ['Max run', number_format($maxMs, 2) . ' ms'],
                ['Avg per item', number_format($avgPerItem, 4) . ' ms'],
            ]
        );

        $this->newLine();
        $this->info("  Tip: Run all 4 strategies separately, then compare averages.");
        $this->newLine();
    }

    private function nsToMs(int|float $ns): float
    {
        return $ns / 1_000_000;
    }
}