<?php

namespace App\Http\Requests\Api\V1\Tag;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
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
             * @example "Laravel"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly slug. Auto-generated from name if empty.
             *
             * @example "laravel"
             */
            'slug' => ['nullable', 'string', 'max:255', 'unique:tags,slug'],
        ];
    }
}