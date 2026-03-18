<?php

use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Health check — no rate limiting, no auth
    Route::get('/health', [MemberController::class, 'health']);
    // Member routes — standard 60/min rate limit
    Route::middleware('throttle:api')->prefix('members')->group(function () {
        Route::post('/create',    [MemberController::class, 'create']);
        Route::get('/{memberId}', [MemberController::class, 'show']);
        // Bulk upload gets its own tighter 10/min limit
        Route::middleware('throttle:bulk-upload')->group(function () {
            Route::post('/bulk-upload', [MemberController::class, 'bulkUpload']);
            Route::get('/bulk-upload/{batchId}/status', [MemberController::class, 'uploadStatus']);
        });
    });
});
