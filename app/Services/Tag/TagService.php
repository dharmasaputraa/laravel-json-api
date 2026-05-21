<?php

namespace App\Services\Tag;

use App\Http\Resources\V1\TagResource;
use App\Models\Tag;
use App\Services\Base\BaseService;
use Spatie\QueryBuilder\AllowedFilter;

class TagService extends BaseService
{
    protected string $model = Tag::class;
    protected string $resource = TagResource::class;
    protected array $allowedIncludes = ['posts', 'posts.category', 'posts.author', 'posts.tags'];
    protected array $allowedSorts = ['name', 'created_at', 'updated_at'];
    protected array $allowedFilters = ['name'];

    // Spatie QueryBuilder config
    protected array $spatieFilters = ['name', 'slug'];
    protected array $allowedFields = [
        'tags.id', 'tags.name', 'tags.slug',
        'tags.description', 'tags.created_at', 'tags.updated_at',
        'posts.id', 'posts.title', 'posts.slug', 'posts.status', 'posts.published_at',
        'users.id', 'users.name', 'users.email',
        'categories.id', 'categories.name', 'categories.slug',
    ];
    protected string $defaultSort = '-created_at';
}
