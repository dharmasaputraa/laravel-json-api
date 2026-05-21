<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title  = $this->faker->unique()->sentence(rand(4, 8));
        $status = $this->faker->randomElement(['draft', 'published', 'published', 'archived']);

        return [
            'title'        => rtrim($title, '.'),
            'slug'         => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'body'         => $this->body(),
            'excerpt'      => $this->faker->sentences(2, true),
            'status'       => $status,
            'is_featured'  => $this->faker->boolean(12),
            'views_count'  => $status === 'published' ? $this->faker->numberBetween(0, 8000) : 0,
            'likes_count'  => $this->faker->numberBetween(0, 500),
            'published_at' => $status === 'published'
                ? $this->faker->dateTimeBetween('-2 years', 'now')
                : null,
            'user_id'      => User::factory(),
            'category_id'  => Category::factory(),
        ];
    }

    // ─── States ────────────────────────────────────────────────

    public function published(): static
    {
        return $this->state(fn () => [
            'status'       => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status'       => 'draft',
            'published_at' => null,
            'views_count'  => 0,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status'       => 'archived',
            'published_at' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function viral(): static
    {
        return $this->state(fn () => [
            'views_count' => $this->faker->numberBetween(10_000, 100_000),
            'likes_count' => $this->faker->numberBetween(500, 5_000),
            'is_featured' => true,
            'status'      => 'published',
            'published_at'=> $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function body(): string
    {
        $paragraphs = $this->faker->paragraphs(rand(4, 8));
        return implode("\n\n", $paragraphs);
    }
}
