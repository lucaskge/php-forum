<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Support\HttpException;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use Closure;

/**
 * `throttle:post` applies the named window from config/security.php, keyed by
 * account when signed in and by address otherwise.
 */
final class RateLimitMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if (!$request->isPost() || $parameter === null) {
            return $next($request);
        }

        $userId = AuthService::instance()->id();
        $key = $parameter . ':' . ($userId !== null ? 'u' . $userId : 'ip' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, $parameter)) {
            $seconds = RateLimiter::availableIn($key);

            throw HttpException::tooManyRequests(sprintf(
                'You are doing that too often. Try again in %d second(s).',
                max(1, $seconds),
            ));
        }

        RateLimiter::hit($key, $parameter);

        return $next($request);
    }
}
