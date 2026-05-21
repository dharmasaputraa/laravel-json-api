<?php

namespace App\Http\Requests\Api\V1\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
             * @example "Getting Started with Laravel"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly slug. Auto-generated if empty.
             *
             * @example "getting-started-laravel"
             */
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],

            /**
             * The post body content.
             *
             * @example "Lorem ipsum dolor sit amet..."
             */
            'body' => ['required', 'string'],

            /**
             * UUID of the category.
             *
             * @example "550e8400-e29b-41d4-a716-446655440000"
             */
            'category_id' => ['required', 'uuid', 'exists:categories,id'],

            /**
             * UUID of the author (user).
             *
             * @example "550e8400-e29b-41d4-a716-446655440001"
             */
            'user_id' => ['required', 'uuid', 'exists:users,id'],

            /**
             * Post status: draft or published.
             *
             * @example "draft"
             */
            'status' => ['nullable', 'in:draft,published'],

            /**
             * Whether the post is featured.
             *
             * @default false
             *
             * @example true
             */
            'is_featured' => ['boolean'],

            /**
             * Publication date.
             *
             * @example "2024-01-15"
             */
            'published_at' => ['nullable', 'date'],

            /**
             * Array of tag UUIDs to associate.
             *
             * @example ["550e8400-e29b-41d4-a716-446655440000"]
             */
            'tags' => ['nullable', 'array'],
            'tags.*' => ['uuid', 'exists:tags,id'],
        ];
    }
}