<?php

namespace App\Services\Category;

use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Services\Base\BaseService;
use Spatie\QueryBuilder\AllowedFilter;

class CategoryService extends BaseService
{
    protected string $model = Category::class;
    protected string $resource = CategoryResource::class;
    protected array $allowedIncludes = ['posts', 'posts.author', 'posts.tags'];
    protected array $allowedSorts = ['name', 'created_at', 'updated_at'];
    protected array $allowedFilters = ['name'];

    // Spatie QueryBuilder config
    protected array $spatieFilters = ['name', 'slug'];
    protected array $allowedFields = [
        'categories.id', 'categories.name', 'categories.slug',
        'categories.description', 'categories.created_at', 'categories.updated_at',
        'posts.id', 'posts.title', 'posts.slug', 'posts.status', 'posts.published_at',
        'users.id', 'users.name', 'users.email',
        'tags.id', 'tags.name', 'tags.slug',
    ];
    protected string $defaultSort = '-created_at';
}
