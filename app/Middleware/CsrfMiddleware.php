<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Csrf;
use App\Support\HttpException;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use Closure;

/**
 * Applied to every state-changing route. Because the board has no JavaScript,
 * the token always arrives as a hidden form field.
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if (!$request->isPost()) {
            return $next($request);
        }

        if (Csrf::verify($request->input(Csrf::FIELD))) {
            return $next($request);
        }

        Logger::security('CSRF token rejected', ['path' => $request->path(), 'ip' => $request->ip()]);

        throw HttpException::tokenMismatch();
    }
}
