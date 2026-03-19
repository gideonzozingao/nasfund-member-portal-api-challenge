<?php

use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Health check — no auth, no rate limiting
    Route::get('/health', [MemberController::class, 'health']);

    // All member routes require a valid Bearer token
    Route::middleware('auth.token')->prefix('members')->group(function () {

        // Bulk upload — tighter 10/min rate limit
        Route::middleware('throttle:bulk-upload')->group(function () {
            Route::post('/bulk-upload', [MemberController::class, 'bulkUpload']);
        });

        // Single member create + show — standard 60/min rate limit
        Route::middleware('throttle:api')->group(function () {
            Route::post('/create',    [MemberController::class, 'create']);
            Route::get('/{memberId}', [MemberController::class, 'show']);
        });

    });

});