<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->post('/transaction', [TransactionController::class, 'store']);

Route::middleware(['auth:sanctum'])->get('/transactions', [TransactionController::class, 'showTransactions']);

Route::middleware(['auth:sanctum'])->get('/categories', [TransactionController::class, 'showCategories']);

Route::middleware(['auth:sanctum'])->get('/cards', [CardController::class, 'showCards']);

Route::middleware(['auth:sanctum'])->post('/cards/store', [CardController::class, 'store']);

Route::middleware(['auth:sanctum'])->get('/cards/flags', [CardController::class, 'showFlags']);

require __DIR__.'/auth.php';