<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

/**
 * Custom JSON:API collection that:
 *
 *  1. Deduplicates `included` resources (spec compliance).
 *  2. Removes the Blade-oriented `meta.links` array from pagination.
 *  3. Rewrites pagination URLs to JSON:API bracket notation
 *     (?page[number]=1&page[size]=5 instead of ?page=1).
 *  4. Adds an ISO 8601 UTC timestamp to `meta`.
 *  5. Converts pagination `meta` keys from snake_case to camelCase.
 */
class BaseJsonApiResourceCollection extends AnonymousResourceCollection
{
    /**
     * Customize the pagination information for JSON:API compliance.
     *
     * Called by Laravel's PaginatedResourceResponse before building the
     * final JSON response. We use it to:
     *  - strip the HTML-only `links` array from `meta`
     *  - rewrite pagination URLs to bracket notation
     *  - add a timestamp
     *  - camelCase the meta keys
     */
    public function paginationInformation($request, array $paginated, array $default): array
    {
        // Build bracket-notation pagination links
        $perPage = $paginated['per_page'] ?? 15;
        $default['links'] = $this->jsonApiPaginationLinks($paginated, $perPage);

        // Transform meta: remove `links`, camelCase keys, add timestamp
        $default['meta'] = $this->jsonApiMeta($paginated);

        return $default;
    }

    /**
     * Rewrite pagination links to JSON:API bracket notation.
     *
     * Converts URLs like ?page=1 into ?page[number]=1&page[size]=5.
     */
    protected function jsonApiPaginationLinks(array $paginated, int $perPage): array
    {
        $page = $paginated['current_page'] ?? 1;

        $buildUrl = function (?string $url, int $pageNumber) use ($perPage): ?string {
            if ($url === null) {
                return null;
            }

            // Parse the URL and replace the page query parameter
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '';
            parse_str($parsed['query'] ?? '', $query);

            // Remove the default `page` parameter
            unset($query['page']);

            // Add bracket-notation parameters
            $query = array_merge($query, [
                'page' => [
                    'number' => $pageNumber,
                    'size' => $perPage,
                ],
            ]);

            return $path . '?' . urldecode(http_build_query($query));
        };

        return [
            'first' => $buildUrl($paginated['first_page_url'] ?? null, 1),
            'last' => $buildUrl($paginated['last_page_url'] ?? null, $paginated['last_page'] ?? 1),
            'prev' => $buildUrl($paginated['prev_page_url'] ?? null, max(1, $page - 1)),
            'next' => $buildUrl($paginated['next_page_url'] ?? null, $page + 1),
        ];
    }

    /**
     * Transform pagination meta:
     *  - Remove the Blade-oriented `links` array
     *  - Convert snake_case keys to camelCase
     *  - Add an ISO 8601 UTC timestamp
     */
    protected function jsonApiMeta(array $paginated): array
    {
        // Remove keys that should not appear in meta
        $meta = array_except($paginated, [
            'data',
            'first_page_url',
            'last_page_url',
            'prev_page_url',
            'next_page_url',
            'links', // Blade HTML pagination links — not needed in API
        ]);

        // Convert snake_case keys to camelCase
        $camelCased = [];
        foreach ($meta as $key => $value) {
            $camelKey = preg_replace_callback('/_([a-z])/', function ($matches) {
                return strtoupper($matches[1]);
            }, $key);
            $camelCased[$camelKey] = $value;
        }

        // Add ISO 8601 UTC timestamp
        $camelCased['timestamp'] = now()->utc()->toIso8601String();

        return $camelCased;
    }

    /**
     * Deduplicate the `included` array by type+id after Laravel builds it.
     *
     * JSON:API spec: "A compound document MUST NOT include more than one
     * resource object for each type and id pair."
     */
    public function with($request): array
    {
        $data = parent::with($request);

        if (empty($data['included'])) {
            return $data;
        }

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