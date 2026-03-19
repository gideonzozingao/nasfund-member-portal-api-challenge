<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register all application bindings.
     * Explicit bindings keep the dependency graph obvious during code review.
     */
    public function register(): void
    {
        // Infrastructure

    }

    public function boot(): void
    {
        // ── Rate limiters ──────────────────────────────────────
        // Defined here because Laravel 12 no longer uses RouteServiceProvider.

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'status' => 'error',
                    'message' => 'Too many requests. Please wait before retrying.',
                    'errors' => null,
                    'data' => null,
                ], 429));
        });

        RateLimiter::for('bulk-upload', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'status' => 'error',
                    'message' => 'Bulk upload rate limit exceeded. Maximum 10 uploads per minute.',
                    'errors' => null,
                    'data' => null,
                ], 429));
        });

    }
}
