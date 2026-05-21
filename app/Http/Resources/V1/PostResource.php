<?php

namespace App\Http\Resources\V1;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * @property Post $resource
 */
class PostResource extends BaseJsonApiResource
{
    protected const JSONAPI_TYPE = 'posts';
    protected const DEFAULT_FIELDS = ['title', 'slug', 'status'];

    protected function allAttributes($request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'views_count' => $this->views_count,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'author' => UserResource::class,
            'category' => CategoryResource::class,
            'tags' => TagResource::class,
        ];
    }

    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.v1.posts.show', ['post' => $this->id]),
        ];
    }
}