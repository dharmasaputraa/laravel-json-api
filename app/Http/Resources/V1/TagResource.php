<?php

namespace App\Http\Resources\V1;

use App\Models\Tag;
use Illuminate\Http\Request;

/**
 * @property Tag $resource
 */
class TagResource extends BaseJsonApiResource
{
    protected const JSONAPI_TYPE = 'tags';
    protected const DEFAULT_FIELDS = ['name', 'slug'];

    protected function allAttributes($request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'posts' => PostResource::class,
        ];
    }

    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.v1.tags.show', ['tag' => $this->id]),
        ];
    }
}