<?php

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
             * @example "Technology"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly slug. Auto-generated from name if empty.
             *
             * @example "technology"
             */
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],

            /**
             * Optional description of the category.
             *
             * @example "All technology-related articles"
             */
            'description' => ['nullable', 'string'],
        ];
    }
}