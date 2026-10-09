<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;


Route::view('/{any?}', 'app')->where('any', '^(?!api).*$');

Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/projects', ProjectController::class);
        Route::post('/chat', ChatController::class)->middleware('throttle:20,1');
    });
});
