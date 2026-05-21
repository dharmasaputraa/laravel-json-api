<?php
// ============================================================
// database/seeders/DatabaseSeeder.php
// ============================================================

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            PostSeeder::class,
        ]);
    }
}

// ============================================================
// database/seeders/UserSeeder.php
// ============================================================

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Fixed demo users for predictable filter testing
        $users = [
            ['name' => 'Alice Tan',     'email' => 'alice@example.com'],
            ['name' => 'Bob Karim',     'email' => 'bob@example.com'],
            ['name' => 'Clara Nguyen',  'email' => 'clara@example.com'],
        ];

        foreach ($users as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => Hash::make('password')])
            );
        }

        // Extra random users to simulate realistic data volume
        User::factory(7)->create();
    }
}

// ============================================================
// database/seeders/CategorySeeder.php
// ============================================================

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Laravel',      'slug' => 'laravel',      'color' => '#ef4444'],
            ['name' => 'PHP',          'slug' => 'php',          'color' => '#8b5cf6'],
            ['name' => 'JavaScript',   'slug' => 'javascript',   'color' => '#f59e0b'],
            ['name' => 'DevOps',       'slug' => 'devops',       'color' => '#10b981'],
            ['name' => 'Architecture', 'slug' => 'architecture', 'color' => '#3b82f6'],
            ['name' => 'Testing',      'slug' => 'testing',      'color' => '#6366f1'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}

// ============================================================
// database/seeders/TagSeeder.php
// ============================================================

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'eloquent', 'query-builder', 'raw-sql', 'spatie',
            'api', 'rest', 'json-api', 'pagination',
            'filtering', 'sorting', 'performance', 'security',
            'migrations', 'seeders', 'factories', 'testing',
            'docker', 'redis', 'queues', 'events',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate(['slug' => $name], ['name' => ucwords(str_replace('-', ' ', $name))]);
        }
    }
}

// ============================================================
// database/seeders/PostSeeder.php
// ============================================================

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Seeds 60 posts spread across all statuses, categories, and date ranges
     * so every filter combination in the four controllers has matching data.
     */
    public function run(): void
    {
        $users      = User::pluck('id')->all();
        $categories = Category::pluck('id', 'slug');
        $tags       = Tag::all();

        // ── 1. Fixed posts for deterministic filter testing ──────────
        $fixed = [
            [
                'title'        => 'Mastering Eloquent Filtering',
                'status'       => 'published',
                'is_featured'  => true,
                'views_count'  => 1240,
                'category'     => 'laravel',
                'published_at' => now()->subDays(5),
                'tags'         => ['eloquent', 'filtering', 'api'],
            ],
            [
                'title'        => 'Query Builder Deep Dive',
                'status'       => 'published',
                'is_featured'  => false,
                'views_count'  => 870,
                'category'     => 'laravel',
                'published_at' => now()->subDays(15),
                'tags'         => ['query-builder', 'filtering', 'performance'],
            ],
            [
                'title'        => 'Raw SQL: When and Why',
                'status'       => 'published',
                'is_featured'  => false,
                'views_count'  => 430,
                'category'     => 'php',
                'published_at' => now()->subDays(30),
                'tags'         => ['raw-sql', 'performance', 'security'],
            ],
            [
                'title'        => 'Spatie Query Builder for APIs',
                'status'       => 'published',
                'is_featured'  => true,
                'views_count'  => 2100,
                'category'     => 'laravel',
                'published_at' => now()->subDays(2),
                'tags'         => ['spatie', 'json-api', 'filtering', 'sorting'],
            ],
            [
                'title'        => 'JSON API Pagination Patterns',
                'status'       => 'published',
                'is_featured'  => false,
                'views_count'  => 560,
                'category'     => 'architecture',
                'published_at' => now()->subDays(45),
                'tags'         => ['json-api', 'pagination', 'rest'],
            ],
            [
                'title'        => 'Draft: Advanced Scopes',
                'status'       => 'draft',
                'is_featured'  => false,
                'views_count'  => 0,
                'category'     => 'laravel',
                'published_at' => null,
                'tags'         => ['eloquent', 'filtering'],
            ],
            [
                'title'        => 'Archived: Old Filtering Tricks',
                'status'       => 'archived',
                'is_featured'  => false,
                'views_count'  => 88,
                'category'     => 'php',
                'published_at' => now()->subMonths(6),
                'tags'         => ['raw-sql', 'query-builder'],
            ],
        ];

        foreach ($fixed as $data) {
            $tagSlugs = $data['tags'];
            unset($data['tags'], $data['category']);

            $post = Post::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                array_merge($data, [
                    'slug'        => Str::slug($data['title']),
                    'body'        => $this->fakebody($data['title']),
                    'excerpt'     => Str::limit($data['title'] . ' — comprehensive guide.', 120),
                    'user_id'     => $users[array_key_first($users)],
                    'category_id' => $categories[$fixed[array_search($data, $fixed) % count($fixed)]['category'] ?? 'laravel'],
                ])
            );

            $post->tags()->sync(
                $tags->whereIn('slug', $tagSlugs)->pluck('id')->all()
            );
        }

        // ── 2. Factory posts for volume (uses PostFactory) ───────────
        $statuses = ['draft', 'published', 'published', 'published', 'archived'];

        for ($i = 0; $i < 53; $i++) {
            $status = $statuses[$i % count($statuses)];

            /** @var Post $post */
            $post = Post::factory()->create([
                'status'       => $status,
                'published_at' => $status === 'published'
                    ? now()->subDays(rand(1, 365))
                    : ($status === 'archived' ? now()->subMonths(rand(7, 24)) : null),
                'user_id'      => $users[array_rand($users)],
                'category_id'  => $categories->random(),
                'views_count'  => $status === 'published' ? rand(10, 5000) : 0,
                'is_featured'  => rand(0, 6) === 0,  // ~14% featured
            ]);

            // Attach 1–4 random tags
            $post->tags()->attach(
                $tags->random(rand(1, 4))->pluck('id')->all()
            );
        }

        $this->command->info('Seeded: ' . Post::count() . ' posts');
    }

    private function fakebody(string $title): string
    {
        return "# {$title}\n\n"
            . "This is a practice post for learning Laravel post filtering techniques. "
            . "It covers Eloquent ORM, Query Builder, raw DB statements, and the Spatie Query Builder package.\n\n"
            . "## Introduction\n\nUnderstanding how to filter data efficiently is essential for any API. "
            . "Each approach has trade-offs in terms of performance, readability, and maintainability.\n\n"
            . "## Key Concepts\n\n- JSON API standard query params\n- Parameterized SQL (no injection)\n"
            . "- Eager loading to prevent N+1\n- Allowlisted sort columns\n- Sparse fieldsets\n";
    }
}
