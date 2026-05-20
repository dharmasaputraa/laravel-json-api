<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    private static int $index = 0;

    private static array $categories = [
        ['name' => 'Laravel', 'description' => 'Articles about the Laravel PHP framework, including Eloquent ORM, Blade templates, and ecosystem tools.'],
        ['name' => 'PHP', 'description' => 'General PHP development topics, from basics to advanced patterns and best practices.'],
        ['name' => 'JavaScript', 'description' => 'Modern JavaScript development covering ES6+, async patterns, and browser APIs.'],
        ['name' => 'DevOps', 'description' => 'CI/CD pipelines, containerization, infrastructure as code, and deployment strategies.'],
        ['name' => 'Database', 'description' => 'Database design, query optimization, migrations, and data modeling techniques.'],
        ['name' => 'Frontend', 'description' => 'UI/UX development, CSS frameworks, component architectures, and responsive design.'],
        ['name' => 'Backend', 'description' => 'Server-side development, API design, authentication, and system architecture.'],
        ['name' => 'Cloud Computing', 'description' => 'AWS, GCP, Azure services, serverless architecture, and cloud-native patterns.'],
        ['name' => 'AI & Machine Learning', 'description' => 'Artificial intelligence, machine learning models, NLP, and data science applied to development.'],
        ['name' => 'Mobile Development', 'description' => 'iOS, Android, and cross-platform mobile app development with React Native and Flutter.'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = self::$categories[self::$index % count(self::$categories)];
        self::$index++;

        return [
            'name' => $category['name'],
            'slug' => \Illuminate\Support\Str::slug($category['name']),
            'description' => $category['description'],
        ];
    }
}