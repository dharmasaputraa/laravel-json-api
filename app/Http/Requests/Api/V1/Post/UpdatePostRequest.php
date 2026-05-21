<?php

namespace App\Http\Requests\Api\V1\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * The post title.
             *
             * @example "Updated Title"
             */
            'title' => ['sometimes', 'string', 'max:255'],

            /**
             * URL-friendly slug.
             *
             * @example "updated-title"
             */
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('posts')->ignore($this->post)],

            /**
             * The post body content.
             *
             * @example "Updated body content..."
             */
            'body' => ['sometimes', 'string'],

            /**
             * UUID of the category.
             *
             * @example "550e8400-e29b-41d4-a716-446655440000"
             */
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],

            /**
             * UUID of the author (user).
             *
             * @example "550e8400-e29b-41d4-a716-446655440001"
             */
            'user_id' => ['sometimes', 'uuid', 'exists:users,id'],

            /**
             * Post status: draft or published.
             *
             * @example "published"
             */
            'status' => ['sometimes', 'in:draft,published'],

            /**
             * Whether the post is featured.
             *
             * @example true
             */
            'is_featured' => ['sometimes', 'boolean'],

            /**
             * Publication date.
             *
             * @example "2024-06-15"
             */
            'published_at' => ['sometimes', 'nullable', 'date'],

            /**
             * Array of tag UUIDs to associate (replaces existing).
             *
             * @example ["550e8400-e29b-41d4-a716-446655440000"]
             */
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['uuid', 'exists:tags,id'],
        ];
    }
}