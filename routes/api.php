<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostFilterAController;
use App\Http\Controllers\Api\V1\PostFilterController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ───────────────────────────────────────────────────────────
    // Post Filtering (4 strategies comparison)
    // ───────────────────────────────────────────────────────────
    // Route::get('/posts/eloquent', [PostFilterController::class, 'eloquent'])->name('posts.eloquent');
    // Route::get('/posts/query-builder', [PostFilterController::class, 'queryBuilder'])->name('posts.query-builder');
    // Route::get('/posts/raw', [PostFilterController::class, 'raw'])->name('posts.raw');
    // Route::get('/posts/spatie', [PostFilterController::class, 'spatie'])->name('posts.spatie');

    // ───────────────────────────────────────────────────────────
    // Post Filtering — Approach A (JsonApiResource returns)
    // ───────────────────────────────────────────────────────────
    // Route::prefix('posts-a')->name('posts-a.')->group(function () {
    //     Route::get('/eloquent', [PostFilterAController::class, 'eloquent'])->name('eloquent');
    //     Route::get('/query-builder', [PostFilterAController::class, 'queryBuilder'])->name('query-builder');
    //     Route::get('/raw', [PostFilterAController::class, 'raw'])->name('raw');
    //     Route::get('/spatie', [PostFilterAController::class, 'spatie'])->name('spatie');
    // });

    // ───────────────────────────────────────────────────────────
    // Approach B: Manual JSON:API envelope (PostManualResource)
    // ───────────────────────────────────────────────────────────
    // Route::prefix('posts-manual')->name('posts-manual.')->group(function () {
    //     Route::get('/', [PostController::class, 'indexManual'])->name('index');
    //     Route::get('/{post}', [PostController::class, 'showManual'])->name('show');
    // });

    // ───────────────────────────────────────────────────────────
    // Approach C ❌ (Anti-pattern): Inline — NO Resource class
    // ───────────────────────────────────────────────────────────
    // Route::prefix('posts-inline')->name('posts-inline.')->group(function () {
    //     Route::get('/', [PostController::class, 'indexInline'])->name('index');
    //     Route::get('/{post}', [PostController::class, 'showInline'])->name('show');
    // });

    // ───────────────────────────────────────────────────────────
    // CRUD Resources (Approach A — JsonApiResource)
    // ───────────────────────────────────────────────────────────

    // Users (read-only)
    Route::apiResource('users', UserController::class)->only(['index', 'show']);

    // Categories (full CRUD)
    Route::apiResource('categories', CategoryController::class);

    // Tags (full CRUD)
    Route::apiResource('tags', TagController::class);

    // Posts (full CRUD — Approach A)
    Route::apiResource('posts', PostController::class);
});
