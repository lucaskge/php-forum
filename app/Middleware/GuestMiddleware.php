<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use Closure;

final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if (AuthService::instance()->check()) {
            return Response::redirect(Url::route('home'));
        }

        return $next($request);
    }
}
