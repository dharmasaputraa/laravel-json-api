<?php

namespace App\Services\Post;

use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

class PostService extends BaseService
{
    protected string $model = Post::class;
    protected string $resource = PostResource::class;
    protected array $allowedIncludes = ['author', 'category', 'tags'];
    protected array $allowedSorts = ['title', 'created_at', 'updated_at', 'published_at', 'views_count'];
    protected array $allowedFilters = ['title', 'status'];

    // Spatie QueryBuilder config
    protected array $allowedFields = [
        'posts.id', 'posts.title', 'posts.slug', 'posts.body',
        'posts.status', 'posts.is_featured', 'posts.views_count',
        'posts.published_at', 'posts.created_at', 'posts.updated_at',
        'users.id', 'users.name', 'users.email',
        'categories.id', 'categories.name', 'categories.slug',
        'tags.id', 'tags.name', 'tags.slug',
    ];
    protected string $defaultSort = '-published_at';

    public function __construct()
    {
        // AllowedFilter objects can't be used in property defaults
        $this->spatieFilters = [
            'title',
            'status',
            'is_featured',
            AllowedFilter::partial('search', 'title'),
            AllowedFilter::scope('published_from'),
            AllowedFilter::scope('published_to'),
            AllowedFilter::exact('tags', 'tags.slug'),
        ];
    }

    /**
     * Create a new post and sync tags.
     */
    public function create(array $data, ?string $userId = null): Model
    {
        $tags = $data['tags'] ?? [];
        unset($data['tags']);

        $post = $this->getModelInstance()->create($data);

        if (!empty($tags)) {
            $post->tags()->sync($tags);
        }

        return $post->fresh(['author', 'category', 'tags']);
    }

    /**
     * Update a post and sync tags.
     */
    public function update(Model $model, array $data, ?string $userId = null): Model
    {
        $tags = null;
        if (array_key_exists('tags', $data)) {
            $tags = $data['tags'];
            unset($data['tags']);
        }

        $model->update($data);

        if ($tags !== null) {
            $model->tags()->sync($tags);
        }

        return $model->fresh(['author', 'category', 'tags']);
    }
}