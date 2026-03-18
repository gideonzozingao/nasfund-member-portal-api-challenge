<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Web routes get the full middleware stack (sessions, CSRF, etc.)
        web: __DIR__ . '/../routes/web.php',

        // ✅ This is the critical line.
        // Declaring routes/api.php here tells Laravel to:
        //   1. Load these routes under the 'api' middleware group
        //   2. Strip VerifyCsrfToken — no CSRF on API routes
        //   3. Apply the /api prefix automatically
        //   4. Use stateless throttle instead of session-based auth
        api: __DIR__ . '/../routes/api.php',

        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Ensure all /api/* routes are always excluded from CSRF verification.
        // This is a safety net in case routes are ever accidentally registered
        // outside the api: key above.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Trust proxies if running behind a load balancer (e.g. Nginx, AWS ALB).
        // Remove if running directly without a proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render all exceptions as JSON when the request expects it
        // (covers Postman, API clients, anything sending Accept: application/json)
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
