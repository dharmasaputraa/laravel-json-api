# API Setup Guide — JSON:API Resources + Scramble Documentation

Complete guide for setting up JSON:API-compliant resources and Scramble API documentation in this project.

---

## Table of Contents

- [Part 1: JSON:API Resource Setup](#part-1-jsonapi-resource-setup)
  - [Prerequisite: JsonApiResource Configuration](#prerequisite-jsonapiresource-configuration)
  - [Project Architecture Overview](#project-architecture-overview)
  - [Approach A: With JsonApiResource (Recommended)](#approach-a-with-jsonapiresource-recommended)
  - [Approach B: Without JsonApiResource (Manual)](#approach-b-without-jsonapiresource-manual)
  - [Base Classes Reference](#base-classes-reference)
  - [Choosing Between Approaches](#choosing-between-approaches)
- [Part 2: Scramble API Documentation Setup](#part-2-scramble-api-documentation-setup)
  - [Installation](#installation)
  - [Configuration: config/scramble.php](#configuration-configscramblephp)
  - [AppServiceProvider Setup](#appserviceprovider-setup)
  - [Manual Annotation Reference](#manual-annotation-reference)
  - [FormRequest Pattern](#formrequest-pattern)
  - [Resource Response Pattern](#resource-response-pattern)
- [Part 3: Step-by-Step — Adding a New Resource](#part-3-step-by-step--adding-a-new-resource)

---

## Part 1: JSON:API Resource Setup

### Prerequisite: JsonApiResource Configuration

In `bootstrap/app.php`, the JSON:API version is configured globally:

```php
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

JsonApiResource::configure('1.0');
```

This tells Laravel to use JSON:API spec version 1.0 for all `JsonApiResource` responses.

### Project Architecture Overview

```
app/Http/
├── Concerns/
│   └── JsonApiResponses.php          # jsonApiMeta() helper for controllers
├── Controllers/Api/
│   ├── BaseApiController.php          # Base controller with JsonApiResponses trait
│   ├── AuthController.php
│   ├── PasswordController.php
│   └── V1/
│       ├── CategoryController.php
│       ├── TagController.php
│       └── GalleryController.php
├── Requests/Api/V1/
│   ├── Category/
│   │   ├── StoreCategoryRequest.php
│   │   └── UpdateCategoryRequest.php
│   └── Tag/
│       ├── StoreTagRequest.php
│       └── UpdateTagRequest.php
└── Resources/V1/
    ├── BaseJsonApiResource.php           # Abstract base (uses JsonApiResource)
    ├── BaseJsonApiResourceCollection.php # Collection with dedup
    ├── CategoryResource.php             # Example: WITHOUT JsonApiResource (manual)
    ├── TagResource.php                  # Example: WITH JsonApiResource
    ├── GalleryResource.php              # Example: WITH JsonApiResource + media
    └── PostResource.php

app/Exceptions/
└── JsonApiExceptionHandler.php          # Global JSON:API error handler

app/Services/
├── Category/
│   └── CategoryService.php
├── Tag/
│   └── TagService.php
└── Gallery/
    └── GalleryService.php

routes/api/
└── v1.php
```

### Approach A: With JsonApiResource (Recommended)

Uses Laravel's built-in `Illuminate\Http\Resources\JsonApi\JsonApiResource` via a custom `BaseJsonApiResource` abstract class. This is the cleanest approach — Laravel handles the JSON:API envelope automatically.

#### Base Class: `BaseJsonApiResource.php`

```php
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
     *
     * Example: 'categories' for CategoryResource → ?fields[categories]=name,slug
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
```

#### Example: `GalleryResource` (Uses Approach A)

```php
<?php

namespace App\Http\Resources\V1;

use App\Models\Gallery;
use Illuminate\Http\Request;

/**
 * @property Gallery $resource
 */
class GalleryResource extends BaseJsonApiResource
{
    protected const JSONAPI_TYPE = 'galleries';
    protected const DEFAULT_FIELDS = ['title', 'slug', 'is_visible', 'photos', 'categories'];

    protected function allAttributes($request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_visible' => $this->is_visible,
            'photos' => $this->resource->getMedia('photos')->map(fn ($media) => [
                'url' => $media->getUrl(),
                'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
                'card' => $media->hasGeneratedConversion('card') ? $media->getUrl('card') : null,
            ])->values()->all(),
            'event_at' => $this->event_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'categories' => CategoryResource::class,
            'tags' => TagResource::class,
            'posts' => PostResource::class,
        ];
    }

    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.v1.galleries.show', ['gallery' => $this->id]),
        ];
    }
}
```

**What you get automatically (handled by Laravel's JsonApiResource):**
- `{ jsonapi: { version: "1.0" } }` envelope
- `{ data: { type, id, attributes, relationships, links } }` structure
- `included` array for relationships (when loaded)
- Sparse fieldset filtering via `?fields[galleries]=title,slug`
- Self links

#### Collection Class: `BaseJsonApiResourceCollection.php`

```php
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
    // Laravel 12's AnonymousResourceCollection already handles
    // deduplication of included resources automatically.
    // This class exists as an extension point for future customization.
}
```

### Approach B: Without JsonApiResource (Manual)

Uses plain `Illuminate\Http\Resources\Json\JsonResource` and manually builds the JSON:API envelope. Useful when you need full control over the response structure.

#### Example: `CategoryResource` (Uses Approach B)

```php
<?php

namespace App\Http\Resources\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Category $resource
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = $this->getRequestedFields($request);
        $allAttributes = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_visible' => $this->is_visible,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        $attributes = $fields
            ? array_intersect_key($allAttributes, array_flip($fields))
            : $allAttributes;

        return [
            'jsonapi' => ['version' => '1.0'],
            'data' => [
                'type' => 'categories',
                'id' => (string) $this->id,
                'attributes' => $attributes,
                'relationships' => $this->when(
                    $this->relationLoaded('posts') || $this->relationLoaded('galleries') || $this->relationLoaded('parent') || $this->relationLoaded('children'),
                    fn () => array_filter([
                        'parent' => $this->whenLoaded('parent', fn () => [
                            'data' => $this->parent ? ['type' => 'categories', 'id' => (string) $this->parent->id] : null,
                        ]),
                        'children' => $this->whenLoaded('children', fn () => [
                            'data' => $this->children->map(fn ($child) => ['type' => 'categories', 'id' => (string) $child->id]),
                        ]),
                        'posts' => $this->whenLoaded('posts', fn () => [
                            'data' => $this->posts->map(fn ($post) => ['type' => 'posts', 'id' => (string) $post->id]),
                        ]),
                        'galleries' => $this->whenLoaded('galleries', fn () => [
                            'data' => $this->galleries->map(fn ($gallery) => ['type' => 'galleries', 'id' => (string) $gallery->id]),
                        ]),
                    ])
                ),
                'links' => [
                    'self' => route('api.v1.categories.show', ['category' => $this->id]),
                ],
            ],
        ];
    }

    protected function getRequestedFields(Request $request): ?array
    {
        $fieldsBag = $request->query->all();
        $fields = $fieldsBag['fields']['categories'] ?? $fieldsBag['fields[categories]'] ?? null;

        if (!is_string($fields) || $fields === '') return null;

        return array_map('trim', explode(',', $fields));
    }
}
```

### Base Classes Reference

#### `BaseApiController` — Shared Controller Helpers

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;

abstract class BaseApiController extends Controller
{
    use JsonApiResponses;
}
```

#### `JsonApiResponses` Trait

```php
<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

trait JsonApiResponses
{
    protected string $jsonApiVersion = '1.0';

    /**
     * Return a JSON:API meta response (used for delete, auth messages, etc.).
     */
    protected function jsonApiMeta(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'jsonapi' => ['version' => $this->jsonApiVersion],
            'meta' => ['message' => $message],
        ], $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}
```

### Choosing Between Approaches

| Aspect | Approach A (JsonApiResource) | Approach B (Manual) |
|--------|-----|-----|
| **Boilerplate** | Minimal — define `allAttributes()`, `toRelationships()`, `toLinks()` | More — manually build entire envelope |
| **Sparse fieldsets** | Automatic via `BaseJsonApiResource` | Manual `getRequestedFields()` per resource |
| **`included` dedup** | Automatic via `BaseJsonApiResourceCollection` | Must handle manually |
| **Relationships** | Laravel resolves resource classes automatically | Must map each relationship manually |
| **Scramble docs** | Needs `@property` hint | Needs `@property` hint |
| **Use when** | Standard resources with relationships | Full control needed, or non-standard responses |

**Recommendation:** Use **Approach A** for all new resources. Only use Approach B if you need custom response structures that don't fit the JSON:API resource pattern.

---

## Part 2: Scramble API Documentation Setup

### Installation

```bash
composer require dedoc/scramble
```

Publish the config file:

```bash
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

### Configuration: `config/scramble.php`

Key settings for this project:

```php
<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    // Base API path — routes starting with this are documented
    'api_path' => 'api',

    // Export path for OpenAPI JSON spec
    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '0.0.1'),
        'description' => '',
    ],

    'ui' => [
        'title' => null,
        'theme' => 'light',
        'hide_try_it' => false,
        'hide_schemas' => false,
        'layout' => 'responsive',
    ],

    // Flatten bracket query params: filter[name] → documented correctly
    'flatten_deep_query_parameters' => true,

    // Restrict docs access in production
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],
];
```

### AppServiceProvider Setup

Scramble is configured with API versioning in `AppServiceProvider::configureScramble()`:

```php
// app/Providers/AppServiceProvider.php

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

protected function configureScramble(): void
{
    Scramble::registerApi('v1', [
        'api_path' => 'api/v1',
        'info' => [
            'version' => '1.0.0',
            'title' => 'Merajan Dalem Batuaya API',
            'description' => 'API documentation for Merajan Dalem Batuaya application.',
        ],
    ])
    ->expose(ui: '/docs/v1', document: '/docs/v1.json')
    ->afterOpenApiGenerated(function (OpenApi $openApi) {
        // Add bearer auth security scheme globally
        $openApi->components->securitySchemes['bearerAuth'] = SecurityScheme::http('bearer');
        $openApi->security[] = new SecurityRequirement('bearerAuth');
    });
}
```

**Access docs at:** `/docs/v1` (UI) and `/docs/v1.json` (OpenAPI spec)

### Manual Annotation Reference

#### Controller-Level PHPDoc

Place on the controller class:

```php
/**
 * @tags Categories
 */
class CategoryController extends BaseApiController
```

| Annotation | Purpose | Example |
|---|---|---|
| `@tags Name` | Group endpoints in docs | `@tags Tags` |

#### Method-Level PHPDoc

```php
/**
 * List all categories.
 *
 * Returns a paginated list of categories with support for filtering, sorting, and including relations.
 *
 * @unauthenticated
 *
 * @response status=200 scenario="success" {"jsonapi":{"version":"1.0"},"data":[...]}
 * @response status=404 scenario="not found" {"jsonapi":{"version":"1.0"},"errors":[{"status":"404",...}]}
 *
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Category not found.
 * @throws \Illuminate\Validation\ValidationException 422 — Validation error.
 */
```

| Annotation | Purpose | Example |
|---|---|---|
| `@unauthenticated` | Mark as public (no auth) | `@unauthenticated` |
| `@response status=200 scenario="..." {...}` | Document specific response | See above |
| `@throws Class code — description` | Document error responses | `@throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Not found` |
| `@deprecated` | Mark endpoint deprecated | `@deprecated` |
| `@operationId name` | Custom operation ID | `@operationId categories.list` |

#### PHP 8 Attributes

```php
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
```

**`#[QueryParameter]`** — For GET query params (index, show):

```php
#[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 2)]
#[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
#[QueryParameter('include', description: 'Comma-separated relations. Allowed: posts,galleries', type: 'string', example: 'posts')]
#[QueryParameter('filter[name]', description: 'Filter by name (partial match)', type: 'string', example: 'news')]
#[QueryParameter('filter[is_visible]', description: 'Filter by visibility', type: 'boolean', example: true)]
#[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: name,created_at', type: 'string', example: '-created_at')]
#[QueryParameter('fields[categories]', description: 'Sparse fieldset. Allowed: name,slug,is_visible', type: 'string', example: 'name,slug')]
public function index(Request $request)
```

**`#[PathParameter]`** — For URL path params (show, update, destroy):

```php
#[PathParameter('category', description: 'Category UUID', type: 'string', format: 'uuid')]
public function show(Request $request, string $category)
```

**`#[BodyParameter]`** — For POST body params (when using `$request->validate()` instead of FormRequest):

```php
#[BodyParameter('email', description: 'User email address', type: 'string', required: true, example: 'user@example.com')]
#[BodyParameter('password', description: 'User password', type: 'string', required: true, example: 'password123')]
public function login(Request $request): JsonResponse
```

> **Note:** When using FormRequest classes (recommended for resources), `#[BodyParameter]` is NOT needed — Scramble reads `rules()` automatically. Only use `#[BodyParameter]` for inline validation (auth endpoints, etc.).

### FormRequest Pattern

Scramble reads `rules()` automatically — PHPDoc annotations inside `rules()` document the request body:

```php
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
             * The category name displayed in listings.
             *
             * @example "Technology News"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly slug. Auto-generated from name if empty.
             *
             * @example "technology-news"
             */
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],

            /**
             * Optional description of the category.
             *
             * @example "All technology-related articles"
             */
            'description' => ['nullable', 'string'],

            /**
             * UUID of the parent category for nested hierarchy.
             *
             * @example "550e8400-e29b-41d4-a716-446655440000"
             */
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id'],

            /**
             * Whether the category is publicly visible.
             *
             * @default true
             *
             * @example true
             */
            'is_visible' => ['boolean'],
        ];
    }
}
```

**Annotations inside `rules()`:**

| Annotation | Effect |
|---|---|
| `/** Block comment */` | Parameter description |
| `@example value` | Example value in docs |
| `@default value` | Default value |
| `@format format` | OpenAPI format hint (e.g., `date-time`, `uuid`) |
| `@var type` | Override type (e.g., `@var 'draft'\|'published'`) |

### Resource Response Pattern

Scramble needs a `@property` hint on the resource class to infer response fields:

```php
/**
 * @property Category $resource
 */
class CategoryResource extends BaseJsonApiResource
```

**Collection responses** (paginated) — Scramble auto-detects paginator:

```php
return CategoryResource::collection($categories);
```

**Single resource responses:**

```php
// Return with 201 status
return (new CategoryResource($category))
    ->response()
    ->setStatusCode(201);

// Return with 200 status
return (new CategoryResource($category))->response();
```

**Meta-only responses** (delete, auth):

```php
return $this->jsonApiMeta('Category deleted successfully.');
```

---

## Part 3: Step-by-Step — Adding a New Resource

Complete walkthrough for adding a new API resource (e.g., `Post`).

### 1. Create the Migration & Model

```bash
php artisan make:migration create_posts_table
php artisan make:model Post
```

### 2. Create the Resource (Approach A — Recommended)

```bash
# Create the resource file
# app/Http/Resources/V1/PostResource.php
```

```php
<?php

namespace App\Http\Resources\V1;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * @property Post $resource
 */
class PostResource extends BaseJsonApiResource
{
    protected const JSONAPI_TYPE = 'posts';
    protected const DEFAULT_FIELDS = ['title', 'slug', 'status'];

    protected function allAttributes($request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toRelationships(Request $request): array
    {
        return [
            'category' => CategoryResource::class,
            'tags' => TagResource::class,
        ];
    }

    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.v1.posts.show', ['post' => $this->id]),
        ];
    }
}
```

### 3. Create FormRequests

```bash
mkdir -p app/Http/Requests/Api/V1/Post
```

**`StorePostRequest.php`:**

```php
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
             * @example "Upacara Odalan di Pura Desa"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * URL-friendly slug. Auto-generated if empty.
             *
             * @example "upacara-odalan-pura-desa"
             */
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],

            'body' => ['required', 'string'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'status' => ['nullable', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
```

**`UpdatePostRequest.php`:**

```php
<?php

namespace App\Http\Requests\Api\V1\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('posts')->ignore($this->post)],
            'body' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'status' => ['sometimes', 'in:draft,published'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
```

### 4. Create the Service

```bash
mkdir -p app/Services/Post
```

```php
<?php

namespace App\Services\Post;

use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use App\Services\Base\BaseService;
use Illuminate\Http\Request;

class PostService extends BaseService
{
    protected string $model = Post::class;
    protected string $resource = PostResource::class;
    protected array $allowedIncludes = ['category', 'tags', 'galleries'];
    protected array $allowedSorts = ['title', 'created_at', 'updated_at', 'published_at'];
    protected array $allowedFilters = ['title', 'status'];
}
```

### 5. Create the Controller

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Post\StorePostRequest;
use App\Http\Requests\Api\V1\Post\UpdatePostRequest;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use App\Services\Post\PostService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Posts
 */
class PostController extends BaseApiController
{
    protected array $allowedIncludes = ['category', 'tags', 'galleries'];

    public function __construct(
        protected PostService $postService
    ) {}

    /**
     * List all posts.
     *
     * Returns a paginated list of posts with support for filtering, sorting, and including relations.
     *
     * @unauthenticated
     */
    #[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 2)]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15, example: 10)]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: category,tags,galleries', type: 'string', example: 'category,tags')]
    #[QueryParameter('filter[title]', description: 'Filter by title (partial match)', type: 'string', example: 'odalan')]
    #[QueryParameter('filter[status]', description: 'Filter by status', type: 'string', example: 'published')]
    #[QueryParameter('sort', description: 'Sort by field. Prefix - for desc. Allowed: title,created_at,updated_at,published_at', type: 'string', example: '-published_at')]
    #[QueryParameter('fields[posts]', description: 'Sparse fieldset. Allowed: title,slug,body,status,published_at,created_at,updated_at', type: 'string', example: 'title,slug')]
    public function index(Request $request)
    {
        $posts = $this->postService->query($request);
        return PostResource::collection($posts);
    }

    /**
     * Create a new post.
     *
     * @unauthenticated
     */
    public function store(StorePostRequest $request)
    {
        $post = $this->postService->create($request->validated(), null);
        return (new PostResource($post))->response()->setStatusCode(201);
    }

    /**
     * Show a single post.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    #[QueryParameter('include', description: 'Comma-separated relations. Allowed: category,tags,galleries', type: 'string', example: 'category,tags')]
    public function show(Request $request, string $post)
    {
        $post = $this->postService->find($post, $request);
        return (new PostResource($post))->response();
    }

    /**
     * Update a post.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    public function update(UpdatePostRequest $request, Post $post)
    {
        $post = $this->postService->update($post, $request->validated(), null);
        return (new PostResource($post->fresh()))->response();
    }

    /**
     * Delete a post.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException 404 — Post not found.
     *
     * @unauthenticated
     */
    #[PathParameter('post', description: 'Post UUID', type: 'string', format: 'uuid')]
    public function destroy(Post $post): JsonResponse
    {
        $this->postService->delete($post, null);
        return $this->jsonApiMeta('Post deleted successfully.');
    }
}
```

### 6. Add Routes

In `routes/api/v1.php`:

```php
use App\Http\Controllers\Api\V1\PostController;

// Public: read-only
Route::apiResource('posts', PostController::class)->only(['index', 'show']);

// Protected: write operations
Route::middleware('auth:api')->group(function () {
    Route::apiResource('posts', PostController::class)->only(['store', 'update', 'destroy']);
});
```

### 7. Verify

```bash
# Check routes are registered
php artisan route:list --path=api/v1/posts

# Check syntax
php -l app/Http/Resources/V1/PostResource.php
php -l app/Http/Controllers/Api/V1/PostController.php

# View docs
# Open /docs/v1 in browser
```

---

## Quick Reference: Scramble Annotation Checklist

When adding a new API endpoint, ensure:

- [ ] **`@tags`** on the controller class (e.g. `@tags Posts`)
- [ ] **`@unauthenticated`** on public methods (remove when auth is implemented)
- [ ] **`#[QueryParameter]`** for each query param on `index`/`show` methods
- [ ] **`#[PathParameter]`** for UUID params on `show`/`update`/`destroy`
- [ ] **`#[BodyParameter]`** for POST body params (only when NOT using FormRequest)
- [ ] **`@throws`** for 404 and other error responses
- [ ] **`@response`** for custom response examples
- [ ] **FormRequest** with PHPDoc annotations in `rules()` for request body docs
- [ ] **`@property Model $resource`** on the Resource class for response docs

## Quick Reference: JSON:API Response Formats

### Single Resource (Approach A — automatic)

```json
{
  "jsonapi": { "version": "1.0" },
  "data": {
    "type": "galleries",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "title": "Upacara Odalan",
      "slug": "upacara-odalan"
    },
    "relationships": {
      "categories": {
        "data": [{ "type": "categories", "id": "..." }]
      }
    },
    "links": {
      "self": "/api/v1/galleries/550e8400-..."
    }
  }
}
```

### Collection (paginated)

```json
{
  "jsonapi": { "version": "1.0" },
  "data": [...],
  "included": [...],
  "links": {
    "self": "/api/v1/galleries?page=1",
    "next": "/api/v1/galleries?page=2",
    "last": "/api/v1/galleries?page=5"
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 72
  }
}
```

### Meta-only (delete)

```json
{
  "jsonapi": { "version": "1.0" },
  "meta": {
    "message": "Category deleted successfully."
  }
}
```

### Error (JsonApiExceptionHandler)

```json
{
  "jsonapi": { "version": "1.0" },
  "errors": [
    {
      "status": "404",
      "title": "Not Found",
      "detail": "The requested category was not found."
    }
  ]
}
```

### Validation Error

```json
{
  "jsonapi": { "version": "1.0" },
  "errors": [
    {
      "status": "422",
      "title": "Validation Error",
      "detail": "The name field is required.",
      "source": { "pointer": "/data/attributes/name" }
    }
  ]
}
```
