<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @property User $resource
 */
class UserResource extends BaseJsonApiResource
{
    protected const JSONAPI_TYPE = 'users';
    protected const DEFAULT_FIELDS = ['name', 'email'];

    protected function allAttributes($request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
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
            'self' => route('api.v1.users.show', ['user' => $this->id]),
        ];
    }
}