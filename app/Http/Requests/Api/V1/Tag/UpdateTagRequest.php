<?php

namespace App\Http\Requests\Api\V1\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * The tag name.
             *
             * @example "Laravel Updated"
             */
            'name' => ['sometimes', 'string', 'max:255'],

            /**
             * URL-friendly slug.
             *
             * @example "laravel-updated"
             */
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tags')->ignore($this->tag)],
        ];
    }
}