<?php

namespace App\Providers;

use App\Actions\CreateMemberAction;
use App\Repositories\MemberRepository;
use App\Services\BulkUploadService;
use App\Services\MemberService;
use App\Utils\CsvParser;
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
        $this->app->singleton(MemberRepository::class);
        $this->app->singleton(CsvParser::class);

        // Actions
        $this->app->singleton(CreateMemberAction::class, function ($app) {
            return new CreateMemberAction(
                $app->make(MemberRepository::class)
            );
        });

        // Services
        $this->app->singleton(MemberService::class, function ($app) {
            return new MemberService(
                $app->make(CreateMemberAction::class)
            );
        });

        $this->app->singleton(BulkUploadService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Too many requests. Please wait before retrying.',
                    'errors'  => null,
                    'data'    => null,
                ], 429));
        });

        RateLimiter::for('bulk-upload', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(fn() => response()->json([
                    'status'  => 'error',
                    'message' => 'Bulk upload rate limit exceeded. Maximum 10 uploads per minute.',
                    'errors'  => null,
                    'data'    => null,
                ], 429));
        });
    }
}
