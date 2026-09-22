<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use Closure;

/**
 * Suspended and banned accounts keep read access but are redirected away from
 * anything that writes, and are shown why.
 */
final class RestrictionMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        $auth = AuthService::instance();

        if ($auth->guest()) {
            return $next($request);
        }

        if ($auth->activeRestriction() === null) {
            return $next($request);
        }

        return Response::redirect(Url::route('account.restricted'));
    }
}
