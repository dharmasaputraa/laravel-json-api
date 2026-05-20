<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────
        User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // ── Categories ─────────────────────────────────────────
        Category::factory(10)->create();

        // ── Tags ───────────────────────────────────────────────
        Tag::factory(20)->create();

        // ── Posts ──────────────────────────────────────────────
        $users = User::all();
        $categories = Category::all();
        $tags = Tag::all();

        // 30 published posts
        Post::factory(30)
            ->published()
            ->create([
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
            ])
            ->each(fn ($post) => $post->tags()->attach($tags->random(rand(1, 3))->pluck('id')->toArray()));

        // 5 published + featured posts
        Post::factory(5)
            ->published()
            ->featured()
            ->create([
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
            ])
            ->each(fn ($post) => $post->tags()->attach($tags->random(rand(1, 3))->pluck('id')->toArray()));

        // 15 draft posts
        Post::factory(15)
            ->draft()
            ->create([
                'user_id' => $users->random()->id,
                'category_id' => $categories->random()->id,
            ])
            ->each(fn ($post) => $post->tags()->attach($tags->random(rand(1, 3))->pluck('id')->toArray()));
    }
}