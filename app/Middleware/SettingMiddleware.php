<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SettingsService;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use Closure;

/**
 * Guards a route behind a board setting: `setting:chat_enabled`.
 *
 * A feature that an administrator has switched off is off for everybody,
 * including staff and administrators themselves. The alternative — letting the
 * people with the most permissions through — means the switch appears not to
 * work to the very person who threw it. Configuration for a feature stays
 * reachable in the administration area, which is guarded by permissions rather
 * than by the feature's own setting.
 *
 * Prefix the setting with `!` to require it to be *off* instead.
 */
final class SettingMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        if ($parameter === null || $parameter === '') {
            return $next($request);
        }

        $expected = true;
        $key = $parameter;

        if (str_starts_with($key, '!')) {
            $expected = false;
            $key = substr($key, 1);
        }

        if (SettingsService::instance()->bool($key, true) === $expected) {
            return $next($request);
        }

        throw new HttpException(503, 'This area is switched off on this board.', 'Unavailable');
    }
}
