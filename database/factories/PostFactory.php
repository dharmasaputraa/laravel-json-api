<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Tech-themed title templates.
     */
    private static array $titleTemplates = [
        'Getting Started with {tech}',
        'Advanced {tech} Patterns and Best Practices',
        'Building Scalable APIs with {tech}',
        '{tech} Performance Optimization Guide',
        'Mastering {tech}: A Deep Dive',
        'Testing {tech} Applications the Right Way',
        'Deploying {tech} to Production',
        '{tech} for Enterprise Applications',
        'Real-time Features with {tech}',
        'Security Best Practices in {tech}',
        'Error Handling Strategies in {tech}',
        'Database Design Patterns with {tech}',
        'Building Microservices with {tech}',
        'Caching Strategies for {tech}',
        'Monitoring and Logging in {tech}',
        '{tech} Authentication and Authorization',
        'Event-Driven Architecture with {tech}',
        'Refactoring Legacy Code to {tech}',
        'Websocket Implementation in {tech}',
        'Building CLI Tools with {tech}',
        'Containerizing {tech} with Docker',
        'CI/CD Pipeline Setup for {tech}',
        'GraphQL vs REST in {tech}',
        'State Management in {tech}',
        'Serverless Functions with {tech}',
    ];

    private static array $technologies = [
        'Laravel', 'PHP 8.3', 'Vue.js', 'React', 'TypeScript', 'Node.js',
        'Docker', 'Kubernetes', 'Redis', 'PostgreSQL', 'MySQL', 'Tailwind CSS',
        'Eloquent ORM', 'REST API', 'GraphQL', 'WebSocket', 'AWS Lambda',
        'Next.js', 'Alpine.js', 'Livewire',
    ];

    private static array $bodyParagraphs = [
        'In this tutorial, we will explore the fundamental concepts behind building robust applications using modern PHP patterns. We will cover dependency injection, service containers, and how to structure your code for maintainability and testability.',
        'One of the most critical aspects of modern web development is understanding how to optimize database queries. By leveraging eager loading, query scopes, and proper indexing, you can significantly reduce response times and improve the overall user experience.',
        'When building APIs, it is essential to follow RESTful conventions and implement proper error handling. This includes using appropriate HTTP status codes, providing meaningful error messages, and implementing rate limiting to protect your endpoints from abuse.',
        'Testing is not an afterthought — it should be an integral part of your development workflow. We will demonstrate how to write unit tests, feature tests, and integration tests using Pest PHP, ensuring your application behaves as expected under various conditions.',
        'Containerization has revolutionized the way we deploy applications. Docker allows you to package your application with all its dependencies, ensuring consistent behavior across development, staging, and production environments.',
        'Security should always be a top priority. Implementing CSRF protection, SQL injection prevention, XSS mitigation, and proper authentication flows are non-negotiable in any modern web application. We will walk through each of these topics in detail.',
        'Performance monitoring and profiling are essential skills for any developer. Using tools like Laravel Telescope, Blackfire, and New Relic, you can identify bottlenecks in your application and optimize critical code paths before they become problems.',
        'Caching is one of the most effective ways to improve application performance. We will cover Redis caching strategies, query result caching, HTTP caching headers, and cache invalidation patterns that ensure your users always see fresh data.',
        'Event-driven architecture enables loose coupling between components in your application. By using event listeners, subscribers, and queued jobs, you can build systems that are both flexible and resilient to failures.',
        'Continuous integration and continuous deployment (CI/CD) pipelines automate the testing and deployment process, reducing human error and enabling faster release cycles. We will set up a complete pipeline using GitHub Actions.',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = str_replace(
            '{tech}',
            fake()->randomElement(self::$technologies),
            fake()->randomElement(self::$titleTemplates)
        );

        $body = collect(range(1, fake()->numberBetween(3, 6)))
            ->map(fn () => fake()->randomElement(self::$bodyParagraphs))
            ->implode("\n\n");

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'body' => $body,
            'status' => PostStatus::Draft,
            'is_featured' => false,
            'published_at' => null,
            'views_count' => fake()->numberBetween(50, 50000),
        ];
    }

    /**
     * Indicate the post is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Indicate the post is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate the post is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}