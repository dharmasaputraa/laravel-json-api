<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

/**
 * Custom JSON:API collection that deduplicates `included` resources.
 *
 * JSON:API spec: "A compound document MUST NOT include more than one
 * resource object for each type and id pair."
 *
 * Laravel's base collection uses `uniqueStrict('_uniqueKey')`, but for
 * BelongsToMany relationships (e.g. tags) Laravel intentionally appends
 * a random suffix to `_uniqueKey` — preventing deduplication. We override
 * `with()` to enforce spec-compliant deduplication by `type:id`.
 */
class BaseJsonApiResourceCollection extends AnonymousResourceCollection
{
    /**
     * Deduplicate the `included` array by type+id after Laravel builds it.
     */
    public function with($request): array
    {
        $data = parent::with($request);

        if (empty($data['included'])) {
            return $data;
        }

        // JSON:API spec: "A compound document MUST NOT include more than one
        // resource object for each type and id pair."
        $seen = [];
        $data['included'] = array_values(array_filter(
            $data['included'],
            function (array $item) use (&$seen): bool {
                $key = "{$item['type']}:{$item['id']}";
                if (isset($seen[$key])) {
                    return false;
                }
                return $seen[$key] = true;
            }
        ));

        return $data;
    }
}