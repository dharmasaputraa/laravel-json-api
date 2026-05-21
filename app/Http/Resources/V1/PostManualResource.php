<?php

namespace App\Http\Resources\V1;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Approach B: Manual JSON:API envelope (without JsonApiResource base).
 *
 * This demonstrates building the JSON:API response structure manually
 * using plain JsonResource. Useful when you need full control.
 *
 * @property Post $resource
 */
class PostManualResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = $this->getRequestedFields($request);
        $allAttributes = [
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'status' => $this->status?->value,
            'is_featured' => $this->is_featured,
            'views_count' => $this->views_count,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $attributes = $fields
            ? array_intersect_key($allAttributes, array_flip($fields))
            : $allAttributes;

        return [
            'jsonapi' => ['version' => '1.0'],
            'data' => [
                'type' => 'posts',
                'id' => (string) $this->id,
                'attributes' => $attributes,
                'relationships' => $this->when(
                    $this->relationLoaded('author') || $this->relationLoaded('category') || $this->relationLoaded('tags'),
                    fn () => array_filter([
                        'author' => $this->whenLoaded('author', fn () => [
                            'data' => $this->author ? ['type' => 'users', 'id' => (string) $this->author->id] : null,
                        ]),
                        'category' => $this->whenLoaded('category', fn () => [
                            'data' => $this->category ? ['type' => 'categories', 'id' => (string) $this->category->id] : null,
                        ]),
                        'tags' => $this->whenLoaded('tags', fn () => [
                            'data' => $this->tags->map(fn ($tag) => ['type' => 'tags', 'id' => (string) $tag->id])->all(),
                        ]),
                    ])
                ),
                'links' => [
                    'self' => route('api.v1.posts-manual.show', ['post' => $this->id]),
                ],
            ],
        ];
    }

    public function with($request): array
    {
        return [
            'jsonapi' => ['version' => '1.0'],
        ];
    }

    /**
     * Parse the sparse fieldset query parameter.
     * Example: ?fields[posts]=title,slug → ['title', 'slug']
     */
    protected function getRequestedFields(Request $request): ?array
    {
        $fieldsBag = $request->query->all();
        $fields = $fieldsBag['fields']['posts'] ?? $fieldsBag['fields[posts]'] ?? null;

        if (!is_string($fields) || $fields === '') return null;

        return array_map('trim', explode(',', $fields));
    }
}