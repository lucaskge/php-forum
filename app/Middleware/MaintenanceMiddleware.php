<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AccessControl;
use App\Services\SettingsService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use Closure;

/**
 * When maintenance mode is on, everybody except administrators sees the
 * offline notice — including on POST routes, so nothing is written meanwhile.
 */
final class MaintenanceMiddleware implements Middleware
{
    /** @var array<int,string> Paths that stay reachable while offline. */
    private const ALLOWED = ['/maintenance', '/login', '/logout'];

    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if (!SettingsService::instance()->bool('maintenance_mode', false)) {
            return $next($request);
        }

        if (AccessControl::instance()->can('admin.access')) {
            return $next($request);
        }

        $path = $request->path();

        if (in_array($path, self::ALLOWED, true) || str_starts_with($path, '/theme/')) {
            return $next($request);
        }

        return Response::redirect(Url::route('maintenance'));
    }
}
