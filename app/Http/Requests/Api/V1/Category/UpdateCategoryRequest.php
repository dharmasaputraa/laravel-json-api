<?php

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * The category name.
             *
             * @example "Technology Updated"
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * URL-friendly slug.
             *
             * @example "technology-updated"
             */
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($this->category)],

            /**
             * Optional description of the category.
             *
             * @example "Updated description"
             */
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}