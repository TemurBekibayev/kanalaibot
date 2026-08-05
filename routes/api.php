<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle']);

Route::middleware('tg.auth')->prefix('mini-app')->group(function () {
    Route::get('/posts', [\App\Http\Controllers\MiniAppApiController::class, 'getPosts']);
    Route::post('/posts/{id}/edit', [\App\Http\Controllers\MiniAppApiController::class, 'editPost']);
    Route::get('/channels', [\App\Http\Controllers\MiniAppApiController::class, 'getChannels']);
    Route::post('/channels/{id}/settings', [\App\Http\Controllers\MiniAppApiController::class, 'saveChannelSettings']);
    Route::get('/stats', [\App\Http\Controllers\MiniAppApiController::class, 'getStats']);
    Route::get('/business/operators', [\App\Http\Controllers\MiniAppApiController::class, 'getOperators']);
});
