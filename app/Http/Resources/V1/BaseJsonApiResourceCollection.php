<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

/**
 * Custom JSON:API collection that deduplicates `included` resources.
 *
 * JSON:API spec: "A compound document MUST NOT include more than one
 * resource object for each type and id pair."
 */
class BaseJsonApiResourceCollection extends AnonymousResourceCollection
{
    // Laravel's AnonymousResourceCollection already handles
    // deduplication of included resources automatically.
    // This class exists as an extension point for future customization.
}