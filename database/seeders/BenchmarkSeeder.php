<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BenchmarkSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed 1000+ posts for benchmarking read queries.
     *
     * Usage: php artisan db:seed --class=BenchmarkSeeder
     */
    public function run(): void
    {
        $this->command->info('Seeding benchmark data (1000 posts)...');

        // ── Reuse or create related data ───────────────────────
        $users = User::all();
        if ($users->count() < 10) {
            $this->command->info('  Creating 50 users...');
            $users = User::factory(50)->create();
        } else {
            $this->command->info("  Using {$users->count()} existing users...");
        }

        $categories = Category::all();
        if ($categories->count() < 5) {
            $this->command->info('  Creating 10 categories...');
            $categories = Category::factory(10)->create();
        } else {
            $this->command->info("  Using {$categories->count()} existing categories...");
        }

        $tags = Tag::all();
        if ($tags->count() < 10) {
            $this->command->info('  Creating 20 tags...');
            $tags = Tag::factory(20)->create();
        } else {
            $this->command->info("  Using {$tags->count()} existing tags...");
        }

        // ── Posts ──────────────────────────────────────────────
        $userIds = $users->pluck('id');
        $categoryIds = $categories->pluck('id');
        $tagIds = $tags->pluck('id');

        // 700 published posts (batched for memory)
        $this->command->info('  Creating 700 published posts...');
        $this->createPostsBatch(700, 'published', false, $userIds, $categoryIds, $tagIds);

        // 100 published + featured posts
        $this->command->info('  Creating 100 published+featured posts...');
        $this->createPostsBatch(100, 'published', true, $userIds, $categoryIds, $tagIds);

        // 200 draft posts
        $this->command->info('  Creating 200 draft posts...');
        $this->createPostsBatch(200, 'draft', false, $userIds, $categoryIds, $tagIds);

        $this->command->info('');
        $this->command->info("✅ Done! Total posts: " . Post::count());
    }

    private function createPostsBatch(
        int $count,
        string $status,
        bool $featured,
        $userIds,
        $categoryIds,
        $tagIds
    ): void {
        // Create in chunks of 100 to avoid memory issues
        $chunkSize = 100;
        $created = 0;

        while ($created < $count) {
            $batch = min($chunkSize, $count - $created);

            $factory = Post::factory($batch);

            if ($status === 'published') {
                $factory = $factory->published();
            } else {
                $factory = $factory->draft();
            }

            if ($featured) {
                $factory = $factory->featured();
            }

            $posts = $factory->create([
                'user_id' => $userIds->random(),
                'category_id' => $categoryIds->random(),
            ]);

            // Attach 1-3 random tags per post
            $posts->each(function ($post) use ($tagIds) {
                $post->tags()->attach(
                    $tagIds->random(rand(1, 3))->toArray()
                );
            });

            $created += $batch;
        }
    }
}
