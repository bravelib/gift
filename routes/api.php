<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Gift\Http\Controllers\GiftController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 🎁 Gift sending API route
Route::post('/gift/group-give', [GiftController::class, 'giveGroupGift']);

// Route::middleware('auth:sanctum')->post('/gift/group-give', [GiftController::class, 'giveGroupGift']);

