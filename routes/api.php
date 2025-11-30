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



Route::prefix('v1/marketing/metrics')->group(function () {
    // (CORS handled globally in public/index.php)
    // Read-only getters remain for inspection
    Route::get('post/{postId}', [MetricsController::class, 'getMetrics']);
    Route::get('campaign/{campaignId}', [MetricsController::class, 'getCampaignMetrics']);
    Route::post('fetch', [MetricsController::class, 'fetchAndUpdateAll']);
});
