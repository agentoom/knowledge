<?php

use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SourceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sources', [SourceController::class, 'index']);
    Route::get('/sources/{id}/schema', [SourceController::class, 'schema']);
    Route::post('/search', [SearchController::class, 'search']);
});
