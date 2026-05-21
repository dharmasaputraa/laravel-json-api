<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Users
 */
class UserController extends BaseApiController
{
    /**
     * List all users.
     *
     * Returns a paginated list of users.
     *
     * @unauthenticated
     */
    #[QueryParameter('page.size', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('filter[name]', description: 'Filter by name (partial match)', type: 'string', example: 'john')]
    #[QueryParameter('filter[email]', description: 'Filter by email (partial match)', type: 'string', example: 'gmail')]
    #[QueryParameter('include', description: 'Comma-separated relations (nested supported). Allowed: posts, posts.category, posts.tags', type: 'string', example: 'posts.category')]
    #[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: name,created_at', type: 'string', example: '-created_at')]
    #[QueryParameter('fields[users]', description: 'Sparse fieldset. Allowed: name,email,email_verified_at,created_at,updated_at', type: 'string', example: 'name,email')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset for posts relation. Allowed: id,title,slug', type: 'string', example: 'title,slug')]
    public function index(Request $request)
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters('name', 'email')
            ->allowedIncludes('posts', 'posts.category', 'posts.tags')
            ->allowedSorts('name', 'created_at')
            ->allowedFields(
                'users.id', 'users.name', 'users.email',
                'users.email_verified_at', 'users.created_at', 'users.updated_at',
                'posts.id', 'posts.title', 'posts.slug', 'posts.status', 'posts.published_at',
                'categories.id', 'categories.name', 'categories.slug',
                'tags.id', 'tags.name', 'tags.slug',
            )
            ->defaultSort('-created_at')
            ->paginate((int) ($request->query('page.size', 15)));

        return UserResource::collection($users);
    }

    /**
     * Show a single user.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — User not found.
     *
     * @unauthenticated
     */
    #[PathParameter('user', description: 'User UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: posts', type: 'string', example: 'posts')]
    public function show(Request $request, string $user)
    {
        $query = User::query();

        if ($includes = $request->query('include')) {
            $relations = array_intersect(
                array_map('trim', explode(',', $includes)),
                ['posts']
            );
            $query->with($relations);
        }

        $user = $query->findOrFail($user);

        return (new UserResource($user))->response();
    }
}