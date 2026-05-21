<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

abstract class BaseJsonApiResource extends JsonApiResource
{
    /**
     * Use custom collection that deduplicates `included` resources.
     */
    public static function collection($resource)
    {
        return new BaseJsonApiResourceCollection($resource, static::class);
    }

    /**
     * The JSON:API type for this resource.
     * Must match the key used in sparse fieldset queries: fields[type].
     * Override in child classes.
     */
    protected const JSONAPI_TYPE = null;

    /**
     * Default fields shown when no ?fields[type]= param is provided.
     * Set to null to show ALL fields by default.
     * Set to an array of field names to limit the default response.
     */
    protected const DEFAULT_FIELDS = null;

    /**
     * Automatically include the JSON:API version object in every response.
     */
    public function with($request): array
    {
        return [
            'jsonapi' => ['version' => '1.0'],
        ];
    }

    /**
     * Attributes filtered by sparse fieldsets.
     * Delegates to allAttributes() and applies filtering.
     */
    public function toAttributes($request): array
    {
        $all = $this->allAttributes($request);
        $requestedFields = $this->getRequestedFields($request);

        if ($requestedFields === null) {
            $defaults = static::DEFAULT_FIELDS;
            if ($defaults === null) {
                return $all;
            }
            return array_intersect_key($all, array_flip($defaults));
        }

        return array_intersect_key($all, array_flip($requestedFields));
    }

    /**
     * Define ALL attributes for this resource.
     * Child classes must override this.
     */
    abstract protected function allAttributes($request): array;

    /**
     * Parse the sparse fieldset query parameter.
     * Example: ?fields[categories]=name,slug → ['name', 'slug']
     */
    protected function getRequestedFields($request): ?array
    {
        $type = static::JSONAPI_TYPE;
        if ($type === null) return null;

        $fieldsBag = $request->query->all();
        $fields = $fieldsBag['fields'][$type] ?? $fieldsBag["fields[{$type}]"] ?? null;

        if (!is_string($fields) || $fields === '') return null;

        return array_map('trim', explode(',', $fields));
    }
}