<?php
// ============================================================
// routes/api.php
// ============================================================

use App\Http\Controllers\Api\PostFilterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Four strategies — same params, different implementation
    Route::get('/posts/eloquent',       [PostFilterController::class, 'eloquent']);
    Route::get('/posts/query-builder',  [PostFilterController::class, 'queryBuilder']);
    Route::get('/posts/raw',            [PostFilterController::class, 'raw']);
    Route::get('/posts/spatie',         [PostFilterController::class, 'spatie']);

});

/*
|──────────────────────────────────────────────────────────────
| SETUP INSTRUCTIONS
|──────────────────────────────────────────────────────────────
|
| 1. INSTALL DEPENDENCIES
|    composer require spatie/laravel-query-builder
|    composer require spatie/laravel-json-api-paginate
|
| 2. PUBLISH SPATIE CONFIG
|    php artisan vendor:publish --provider="Spatie\QueryBuilder\QueryBuilderServiceProvider" --tag="query-builder-config"
|    php artisan vendor:publish --provider="Spatie\JsonApiPaginate\JsonApiPaginateServiceProvider"
|
| 3. RUN MIGRATIONS
|    php artisan migrate
|
| 4. SEED DATABASE
|    php artisan db:seed
|    # or fresh seed:
|    php artisan migrate:fresh --seed
|
| 5. QUICK SMOKE TEST (httpie or curl)
|
|    # All published posts, newest first
|    http GET localhost:8000/api/v1/posts/spatie \
|         "filter[status]==published" \
|         "sort==-published_at" \
|         "page[number]==1" \
|         "page[size]==5"
|
|    # Featured posts with author included
|    http GET localhost:8000/api/v1/posts/eloquent \
|         "filter[status]==published" \
|         "filter[is_featured]==true" \
|         "include==author,tags"
|
|    # Search + tag filter
|    http GET localhost:8000/api/v1/posts/query-builder \
|         "filter[search]==laravel" \
|         "filter[tags][]==eloquent" \
|         "filter[tags][]==api"
|
|    # Date range (raw SQL)
|    http GET localhost:8000/api/v1/posts/raw \
|         "filter[status]==published" \
|         "filter[published_from]==2024-01-01" \
|         "filter[published_to]==2024-06-30" \
|         "sort==-views_count"
|
|──────────────────────────────────────────────────────────────
| FILE PLACEMENT GUIDE
|──────────────────────────────────────────────────────────────
|
| migrations/
|   2024_01_01_000001_create_categories_table.php
|   2024_01_01_000002_create_tags_table.php
|   2024_01_01_000003_create_posts_table.php
|   2024_01_01_000004_create_post_tag_table.php
|
| app/Models/
|   Category.php
|   Tag.php
|   PostModel.php  → rename to Post.php (replaces default)
|
| app/Http/Controllers/Api/
|   PostFilterController.php
|
| app/Http/Resources/
|   PostResource.php  (from previous output)
|
| database/factories/
|   CategoryFactory.php
|   PostFactory.php
|
| database/seeders/
|   DatabaseSeeder.php  (contains all 4 seeders inline)
|
| routes/
|   api.php
|
*/
