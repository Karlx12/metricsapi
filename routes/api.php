<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MetricsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1/marketing/metrics')->group(function () {
    Route::get('post/{postId}', [MetricsController::class, 'getMetrics']);
    Route::post('post/{postId}/update', [MetricsController::class, 'updateMetrics']);
    Route::get('campaign/{campaignId}', [MetricsController::class, 'getCampaignMetrics']);
});
