<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CsvImportController;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
*/

/* ----- Public ----- */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

/* ----- Protected (needs Bearer token) ----- */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('products', ProductController::class);
});

Route::middleware('auth:sanctum')
    ->post('/import-csv', [CsvImportController::class, 'store'])
    ->name('csv.import');

