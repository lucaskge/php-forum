<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AccessControl;
use App\Services\AuthService;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Url;
use Closure;

/**
 * Route guard for a granular permission: `can:admin.settings`.
 * Several slugs may be given separated by `|`, meaning "any of".
 */
final class PermissionMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if ($parameter === null || $parameter === '') {
            return $next($request);
        }

        $access = AccessControl::instance();
        $permissions = explode('|', $parameter);

        if ($access->canAny($permissions)) {
            return $next($request);
        }

        if (AuthService::instance()->guest()) {
            if ($request->method() === 'GET') {
                Session::put('__intended', $request->path());
            }

            return Response::redirect(Url::route('auth.login.show'));
        }

        throw HttpException::forbidden('Your account does not hold the permission required for this area.');
    }
}
