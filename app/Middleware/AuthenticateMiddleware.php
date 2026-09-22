<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Support\Flash;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Url;
use Closure;

final class AuthenticateMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if (AuthService::instance()->check()) {
            return $next($request);
        }

        // Remember where the visitor was heading so login can return them.
        if ($request->method() === 'GET') {
            Session::put('__intended', $request->path());
        }

        Flash::warning('Sign in to continue.');

        return Response::redirect(Url::route('auth.login.show'));
    }
}
