# Read Posts Benchmark

Compare the performance of 4 query strategies for reading posts.

## Strategies

| Strategy | Description |
|---|---|
| **Eloquent** | `Post::where(...)->paginate()` — full ORM |
| **QueryBuilder** | `DB::table('posts')->...->paginate()` — Laravel query builder, no models |
| **Raw SQL** | `DB::select("SELECT ...")` — raw SQL, maximum control |
| **Spatie** | `QueryBuilder::for(Post::class)->...` — spatie/laravel-query-builder |

## Two Benchmark Modes

### Mode 1: Without Relations (default) — Pure query engine comparison
All strategies fetch **only** `posts.*`. No joins, no eager loading. This isolates the query builder overhead.

**Each strategy runs exactly 1 query:**
```
SELECT * FROM posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 1000
```

### Mode 2: With Relations (`--with-relations`) — Real-world comparison
All strategies fetch posts **AND** their related author, category, and tags. Same data output.

**Each strategy runs exactly 4 queries:**
```
1. SELECT * FROM posts WHERE ...
2. SELECT * FROM users WHERE id IN (...)
3. SELECT * FROM categories WHERE id IN (...)
4. SELECT tags.*, post_tag.post_id FROM tags JOIN post_tag ... WHERE post_id IN (...)
```

---

## Step 1 — Seed 1000 Posts

```bash
# Fresh database + default data + 1000 benchmark posts
php artisan migrate:fresh --seed
php artisan db:seed --class=BenchmarkSeeder
```

> **What gets created:**
> - 50 users / 20 categories / 30 tags (reuses existing if present)
> - 700 published + 100 published+featured + 200 draft posts
> - Each post has 1–3 random tags

---

## Step 2 — Run Benchmarks

### Mode 1: Posts only (default)

```bash
php artisan benchmark:read-posts --strategy=eloquent --runs=10 --items=1000
php artisan benchmark:read-posts --strategy=query_builder --runs=10 --items=1000
php artisan benchmark:read-posts --strategy=raw --runs=10 --items=1000
php artisan benchmark:read-posts --strategy=spatie --runs=10 --items=1000
```

### Mode 2: Posts + Relations

```bash
php artisan benchmark:read-posts --strategy=eloquent --runs=10 --items=1000 --with-relations
php artisan benchmark:read-posts --strategy=query_builder --runs=10 --items=1000 --with-relations
php artisan benchmark:read-posts --strategy=raw --runs=10 --items=1000 --with-relations
php artisan benchmark:read-posts --strategy=spatie --runs=10 --items=1000 --with-relations
```

### One-liner: All strategies

```bash
# Posts only
for s in eloquent query_builder raw spatie; do
  echo "=== $s ===" && php artisan benchmark:read-posts --strategy=$s --runs=10 --items=1000 && echo ""
done

# With relations
for s in eloquent query_builder raw spatie; do
  echo "=== $s ===" && php artisan benchmark:read-posts --strategy=$s --runs=10 --items=1000 --with-relations && echo ""
done
```

### Interactive mode (no --strategy)
```bash
php artisan benchmark:read-posts
# Prompts you to pick a strategy
```

---

## CLI Options

| Option | Default | Description |
|---|---|---|
| `--strategy` | *(interactive)* | `eloquent`, `query_builder`, `raw`, `spatie` |
| `--runs=N` | `10` | Number of measured iterations |
| `--items=N` | `1000` | Page size (items fetched per run) |
| `--warmup=N` | `2` | Warmup runs before measuring |
| `--with-relations` | `false` | Include author + category + tags |
| `--seed` | `false` | Auto-seed 1K posts if DB has < 1000 |

---

## Example Output

```
  ╔══════════════════════════════════════════════════╗
  ║     Read Posts Benchmark — Single Strategy      ║
  ╚══════════════════════════════════════════════════╝

  Strategy      : eloquent
  Mode          : WITHOUT relations (posts only)
  Runs          : 10
  Items/run     : 1000
  Warmup runs   : 2
  Total posts in DB : 1050

  ╔═════════════════════════════════════════════════════════╗
  ║                    RESULTS                             ║
  ╚═════════════════════════════════════════════════════════╝

  ┌─────────────────┬──────────────────┐
  │ Metric          │ Value            │
  ├─────────────────┼──────────────────┤
  │ Strategy        │ eloquent         │
  │ Mode            │ without relations│
  │ Runs            │ 10               │
  │ Items per run   │ 800              │
  │ ─── Timing ───  │ ───              │
  │ Total time      │ 1520.30 ms       │
  │ Avg per run     │ 152.03 ms        │
  │ Min run         │ 140.12 ms        │
  │ Max run         │ 178.45 ms        │
  │ Avg per item    │ 0.1900 ms        │
  └─────────────────┴──────────────────┘
```

---

## How It Works

1. **DB connection warmup** — `SELECT 1` establishes connection before measuring
2. **Strategy warmup** — N runs to prime MySQL/PHP caches (not measured)
3. **Measured runs** — `hrtime(true)` nanosecond timer with `gc_collect_cycles()` between runs
4. **Fair comparison** — each strategy runs in its own process

### Why One Strategy Per Run?

Running all 4 in one process is **unfair** because:
- Strategy #1 pays the cold DB connection cost
- MySQL query cache benefits later strategies
- PHP memory grows over time

---

## Files

| File | Purpose |
|---|---|
| `app/Console/Commands/BenchmarkReadPosts.php` | The artisan command |
| `database/seeders/BenchmarkSeeder.php` | Seeds 1000 posts + relations |
| `dev-docs/benchmark/read-posts-benchmark.md` | This file |

---

## Tips for Accurate Results

- **More runs = more reliable**: use `--runs=20` or `--runs=50`
- **Warmup matters**: `--warmup=3` ensures caches are primed
- **Run multiple times**: discard outliers
- **Same conditions**: don't run heavy background processes
- **Production-like**: use a real database (PostgreSQL/MySQL), not SQLite