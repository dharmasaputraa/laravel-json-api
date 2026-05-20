<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    private static int $index = 0;

    private static array $tags = [
        'eloquent',
        'api',
        'vue',
        'react',
        'tailwind',
        'docker',
        'kubernetes',
        'mysql',
        'postgresql',
        'redis',
        'testing',
        'deployment',
        'security',
        'performance',
        'typescript',
        'node',
        'rest',
        'graphql',
        'websocket',
        'caching',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = self::$tags[self::$index % count(self::$tags)];
        self::$index++;

        return [
            'name' => $name,
            'slug' => $name,
        ];
    }
}