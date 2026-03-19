<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $header = $request->header('Authorization');

        // 1. Header must be present and well-formed
        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header.');
        }

        $plain = substr($header, 7); // strip "Bearer " prefix cleanly

        // 2. Token must exist, be active, and not expired
        /** @var ApiToken|null $token */
        $token = ApiToken::where('token', $plain)
            ->where('is_active', true)
            ->first();

        if (! $token) {
            return $this->unauthorized('Invalid or revoked token.');
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return $this->unauthorized('Token has expired.');
        }

        // 3. Stamp last activity — fire-and-forget, never block the request
        $token->updateQuietly(['last_used_at' => now()]);

        // 4. Make the resolved token available to controllers if needed
        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => null,
            'data'    => null,
        ], 401);
    }
}