<?php

use App\Http\Controllers\Api\PostFilterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Four strategies — same params, different implementation
    Route::get('/posts/eloquent', [PostFilterController::class, 'eloquent']);
    Route::get('/posts/query-builder', [PostFilterController::class, 'queryBuilder']);
    Route::get('/posts/raw', [PostFilterController::class, 'raw']);
    Route::get('/posts/spatie', [PostFilterController::class, 'spatie']);

});